import { computed, ref, watch } from 'vue'
import { route } from 'ziggy-js'

function csrfHeaders() {
  const headers = {
    Accept: 'application/json',
    'Content-Type': 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
  }
  const meta = document.head.querySelector('meta[name="csrf-token"]')?.content
  if (meta) headers['X-CSRF-TOKEN'] = meta
  const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]*)/)
  if (match) headers['X-XSRF-TOKEN'] = decodeURIComponent(match[1])
  return headers
}

function money(value) {
  const n = Number.parseFloat(value ?? 0)
  return Number.isFinite(n) ? n.toFixed(2) : '0.00'
}

function lineTotal(price, qty) {
  return (Number.parseFloat(price ?? 0) * Number.parseFloat(qty ?? 0)).toFixed(2)
}

export function useCanteenPos({ list = [], categories = [] } = {}) {
  const productSearch = ref('')
  const barcodeSearch = ref('')
  const categoryFilter = ref('')
  const studentQuery = ref('')
  const studentResults = ref([])
  const studentSearched = ref(false)
  const selectedStudent = ref(null)
  const eligibility = ref(null)
  const eligibilityError = ref('')
  const cart = ref([])
  const discount = ref('0')
  const busy = ref(false)
  const message = ref('')
  const validatingCart = ref(false)
  const validationError = ref('')
  const lastSale = ref(null)
  const showReceipt = ref(false)
  const restrictionBlocks = ref([])
  const restrictionWarnings = ref([])
  const dailyLimitWarning = ref('')
  const walletWarning = ref('')
  const canCheckoutFlag = ref(false)

  const catalogSource = ref(Array.isArray(list) ? [...list] : [])

  const canSelectProducts = computed(() => !!selectedStudent.value)

  const catalog = computed(() => {
    const q = productSearch.value.trim().toLowerCase()
    return catalogSource.value.filter((product) => {
      if (categoryFilter.value && product.category_id !== categoryFilter.value) {
        return false
      }
      if (!q) return true
      const haystack = [
        product.name,
        product.name_ar,
        product.sku,
        product.barcode,
      ].filter(Boolean).join(' ').toLowerCase()
      return haystack.includes(q)
    })
  })

  const itemCount = computed(() =>
    cart.value.reduce((sum, line) => sum + Number.parseFloat(line.quantity ?? 0), 0),
  )

  const subtotal = computed(() =>
    cart.value.reduce((sum, line) => sum + Number.parseFloat(line.line_total ?? 0), 0),
  )

  const total = computed(() => {
    const value = Math.max(0, subtotal.value - Number.parseFloat(discount.value || 0))
    return money(value)
  })

  const limitStatus = computed(() => {
    const remaining = eligibility.value?.daily_limit?.remaining
    const limit = eligibility.value?.daily_limit?.limit
    if (remaining == null || limit == null) return 'neutral'
    const rem = Number.parseFloat(remaining)
    const lim = Number.parseFloat(limit)
    if (!Number.isFinite(rem) || !Number.isFinite(lim) || lim <= 0) return 'success'
    const ratio = rem / lim
    if (rem <= 0) return 'danger'
    if (ratio <= 0.2) return 'warning'
    return 'success'
  })

  const canCheckout = computed(() =>
    canCheckoutFlag.value
    && !!selectedStudent.value
    && cart.value.length > 0
    && !busy.value
    && !validatingCart.value
    && !validationError.value,
  )

  function productImageUrl(path) {
    if (!path) return null
    if (path.startsWith('http://') || path.startsWith('https://') || path.startsWith('/')) {
      return path
    }
    return `/storage/${path}`
  }

  function isHealthy(product) {
    return (product?.restriction_tags ?? []).includes('healthy')
  }

  function stockState(product) {
    const onHand = Number.parseFloat(product?.on_hand ?? 0)
    if (!Number.isFinite(onHand) || onHand <= 0) {
      return { key: 'out', label: 'نفد', class: 'bg-secondary' }
    }
    if (onHand <= 5) {
      return { key: 'low', label: `متبقي ${onHand}`, class: 'bg-warning text-dark' }
    }
    return { key: 'ok', label: `متوفر ${onHand}`, class: 'bg-success' }
  }

  function cartPayload() {
    return cart.value.map((line) => ({
      product_id: line.product_id,
      quantity: line.quantity,
    }))
  }

  async function searchStudents() {
    const q = studentQuery.value.trim()
    studentSearched.value = true
    studentResults.value = []
    if (!q) return

    try {
      const { data } = await window.axios.get(route('canteen.api.students.search'), {
        params: { q },
        headers: csrfHeaders(),
      })
      studentResults.value = Array.isArray(data) ? data : []
    } catch (e) {
      studentResults.value = []
      message.value = e.response?.data?.message || 'تعذر البحث عن الطلاب'
    }
  }

  function pickStudentFromResults() {
    if (studentResults.value.length === 1) {
      selectStudent(studentResults.value[0])
    }
  }

  async function loadEligibility(student) {
    eligibilityError.value = ''
    eligibility.value = null
    if (!student?.student_id_ref) return

    try {
      const { data } = await window.axios.get(
        route('canteen.api.students.eligibility', student.student_id_ref),
        { headers: csrfHeaders() },
      )
      eligibility.value = data
    } catch (e) {
      eligibilityError.value = e.response?.data?.message || 'تعذر تحميل بيانات الطالب'
    }
  }

  async function selectStudent(student) {
    selectedStudent.value = student
    studentResults.value = []
    studentSearched.value = false
    await loadEligibility(student)
    await validateCartState()
  }

  function clearStudent() {
    selectedStudent.value = null
    eligibility.value = null
    eligibilityError.value = ''
    cart.value = []
    restrictionBlocks.value = []
    restrictionWarnings.value = []
    dailyLimitWarning.value = ''
    walletWarning.value = ''
    canCheckoutFlag.value = false
    validationError.value = ''
  }

  async function retryEligibility() {
    if (selectedStudent.value) {
      await loadEligibility(selectedStudent.value)
    }
  }

  async function checkProductBlock(product) {
    if (!selectedStudent.value?.student_id_ref) return null

    try {
      const { data } = await window.axios.get(
        route('canteen.api.students.product-block', selectedStudent.value.student_id_ref),
        {
          params: { product_id: product.id },
          headers: csrfHeaders(),
        },
      )
      return data?.blocked ? data.violation?.message : null
    } catch {
      return null
    }
  }

  async function addToCart(product) {
    if (!selectedStudent.value) return

    const blockMessage = await checkProductBlock(product)
    if (blockMessage) {
      message.value = blockMessage
      return
    }

    const stock = stockState(product)
    if (stock.key === 'out') {
      message.value = 'المنتج غير متوفر في المخزون'
      return
    }

    const existing = cart.value.find((line) => line.product_id === product.id)
    if (existing) {
      existing.quantity = Number.parseFloat(existing.quantity) + 1
      existing.line_total = lineTotal(existing.unit_price, existing.quantity)
    } else {
      cart.value.push({
        product_id: product.id,
        product_name: product.name_ar || product.name,
        unit_price: product.selling_price,
        quantity: 1,
        line_total: lineTotal(product.selling_price, 1),
      })
    }

    message.value = ''
    await validateCartState()
  }

  async function lookupBarcode() {
    const code = barcodeSearch.value.trim()
    if (!code) return

    try {
      const { data } = await window.axios.get(route('canteen.api.barcode', code), {
        headers: csrfHeaders(),
      })
      const product = data?.data ?? data
      if (product) {
        await addToCart(product)
        barcodeSearch.value = ''
      }
    } catch {
      message.value = 'لم يتم العثور على المنتج'
    }
  }

  function changeQty(line, delta) {
    const target = cart.value.find((item) => item.product_id === line.product_id)
    if (!target) return

    const nextQty = Number.parseFloat(target.quantity) + delta
    if (nextQty <= 0) {
      removeLine(line)
      return
    }

    target.quantity = nextQty
    target.line_total = lineTotal(target.unit_price, target.quantity)
    validateCartState()
  }

  function removeLine(line) {
    cart.value = cart.value.filter((item) => item.product_id !== line.product_id)
    validateCartState()
  }

  async function validateCartState() {
    restrictionBlocks.value = []
    restrictionWarnings.value = []
    dailyLimitWarning.value = ''
    walletWarning.value = ''
    canCheckoutFlag.value = false
    validationError.value = ''

    if (!selectedStudent.value || cart.value.length === 0) {
      return
    }

    validatingCart.value = true
    try {
      const { data } = await window.axios.post(
        route('canteen.api.cart.validate'),
        {
          student_id_ref: selectedStudent.value.student_id_ref,
          items: cartPayload(),
        },
        { headers: csrfHeaders() },
      )

      const restrictions = data?.restrictions ?? {}
      restrictionBlocks.value = restrictions.blocks ?? []
      restrictionWarnings.value = restrictions.warnings ?? []

      const dailyLimit = data?.daily_limit ?? {}
      if (dailyLimit.allowed === false) {
        dailyLimitWarning.value = 'تجاوز الطالب الحد اليومي المسموح'
      }

      const wallet = data?.wallet ?? {}
      walletWarning.value = wallet.allowed === false ? (wallet.message || 'رصيد المحفظة غير كافٍ') : ''

      canCheckoutFlag.value = !!data?.can_checkout
    } catch (e) {
      validationError.value = e.response?.data?.message || 'تعذر التحقق من السلة'
      canCheckoutFlag.value = false
    } finally {
      validatingCart.value = false
    }
  }

  async function retryValidation() {
    await validateCartState()
  }

  async function checkout() {
    if (!canCheckout.value || !selectedStudent.value) return

    busy.value = true
    message.value = ''
    try {
      const { data } = await window.axios.post(
        route('canteen.api.sales.store'),
        {
          student_id_ref: selectedStudent.value.student_id_ref,
          items: cartPayload(),
          discount: discount.value || '0',
        },
        { headers: csrfHeaders() },
      )
      lastSale.value = data?.data ?? data
      showReceipt.value = true
      message.value = 'تمت عملية البيع بنجاح'
      await loadEligibility(selectedStudent.value)
    } catch (e) {
      message.value = e.response?.data?.message || 'تعذر إتمام عملية البيع'
    } finally {
      busy.value = false
    }
  }

  function newSale() {
    showReceipt.value = false
    lastSale.value = null
    cart.value = []
    discount.value = '0'
    message.value = ''
    restrictionBlocks.value = []
    restrictionWarnings.value = []
    dailyLimitWarning.value = ''
    walletWarning.value = ''
    canCheckoutFlag.value = false
    validationError.value = ''
    if (selectedStudent.value) {
      loadEligibility(selectedStudent.value)
    }
  }

  watch(discount, () => {
    if (cart.value.length) {
      validateCartState()
    }
  })

  if (categories.length && !categoryFilter.value) {
    categoryFilter.value = ''
  }

  return {
    productSearch,
    barcodeSearch,
    categoryFilter,
    studentQuery,
    studentResults,
    studentSearched,
    selectedStudent,
    eligibility,
    cart,
    discount,
    busy,
    message,
    validatingCart,
    validationError,
    eligibilityError,
    lastSale,
    showReceipt,
    canSelectProducts,
    catalog,
    itemCount,
    subtotal,
    total,
    restrictionBlocks,
    restrictionWarnings,
    limitStatus,
    dailyLimitWarning,
    walletWarning,
    canCheckout,
    searchStudents,
    pickStudentFromResults,
    selectStudent,
    clearStudent,
    retryEligibility,
    addToCart,
    lookupBarcode,
    changeQty,
    removeLine,
    checkout,
    newSale,
    productImageUrl,
    isHealthy,
    stockState,
    retryValidation,
  }
}
