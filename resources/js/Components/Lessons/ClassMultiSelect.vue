<script setup>
import { computed, ref } from 'vue'

const props = defineProps({
    modelValue: { type: Array, default: () => [] },
    options: { type: Array, default: () => [] },
    disabled: { type: Boolean, default: false },
    emptyText: { type: String, default: 'لا توجد فصول متاحة للاختيار.' },
    searchPlaceholder: { type: String, default: 'ابحث عن فصل...' },
})

const emit = defineEmits(['update:modelValue'])

const search = ref('')

const normalizedOptions = computed(() =>
    (props.options || []).map((opt) => ({
        id: Number(opt.id),
        name: opt.name ?? String(opt.id),
    }))
)

const filteredOptions = computed(() => {
    const q = search.value.trim().toLowerCase()
    if (!q) return normalizedOptions.value
    return normalizedOptions.value.filter((opt) => opt.name.toLowerCase().includes(q))
})

const selectedIds = computed(() =>
    (props.modelValue || []).map((id) => Number(id))
)

const selectedCount = computed(() => selectedIds.value.length)

const showSearch = computed(() => normalizedOptions.value.length > 5)

function isSelected(id) {
    return selectedIds.value.includes(Number(id))
}

function toggle(id) {
    if (props.disabled) return
    const numId = Number(id)
    const current = [...selectedIds.value]
    const index = current.indexOf(numId)
    if (index === -1) {
        current.push(numId)
    } else {
        current.splice(index, 1)
    }
    emit('update:modelValue', current)
}

function selectAllVisible() {
    if (props.disabled) return
    const merged = new Set([...selectedIds.value, ...filteredOptions.value.map((o) => o.id)])
    emit('update:modelValue', [...merged])
}

function clearAll() {
    if (props.disabled) return
    emit('update:modelValue', [])
}
</script>

<template>
    <div class="class-multi-select" :class="{ 'is-disabled': disabled }">
        <div v-if="showSearch || normalizedOptions.length" class="class-multi-select__toolbar">
            <div v-if="showSearch" class="class-multi-select__search">
                <span class="input-group-text bg-transparent border-end-0">
                    <i class="bi bi-search text-muted"></i>
                </span>
                <input
                    v-model="search"
                    type="search"
                    class="form-control border-start-0 ps-0"
                    :placeholder="searchPlaceholder"
                    :disabled="disabled"
                >
            </div>
            <div class="class-multi-select__actions">
                <span v-if="selectedCount" class="badge bg-primary-subtle text-primary border">
                    {{ selectedCount }} محدد
                </span>
                <button
                    v-if="filteredOptions.length && !disabled"
                    type="button"
                    class="btn btn-sm btn-light"
                    @click="selectAllVisible"
                >
                    تحديد الكل
                </button>
                <button
                    v-if="selectedCount && !disabled"
                    type="button"
                    class="btn btn-sm btn-light text-danger"
                    @click="clearAll"
                >
                    إلغاء الكل
                </button>
            </div>
        </div>

        <div v-if="normalizedOptions.length === 0" class="class-multi-select__empty">
            <i class="bi bi-inbox text-muted"></i>
            <span>{{ emptyText }}</span>
        </div>

        <div v-else-if="filteredOptions.length === 0" class="class-multi-select__empty">
            <i class="bi bi-search text-muted"></i>
            <span>لا توجد نتائج مطابقة للبحث.</span>
        </div>

        <div v-else class="class-multi-select__grid">
            <button
                v-for="opt in filteredOptions"
                :key="opt.id"
                type="button"
                class="class-multi-select__chip"
                :class="{ 'is-selected': isSelected(opt.id) }"
                :disabled="disabled"
                @click="toggle(opt.id)"
            >
                <span class="class-multi-select__check">
                    <i v-if="isSelected(opt.id)" class="bi bi-check-lg"></i>
                </span>
                <span class="class-multi-select__label">{{ opt.name }}</span>
            </button>
        </div>
    </div>
</template>

<style scoped>
.class-multi-select {
    border: 1px solid var(--bs-border-color, #dee2e6);
    border-radius: 0.75rem;
    background: var(--bs-body-bg, #fff);
    padding: 0.85rem;
}

.class-multi-select.is-disabled {
    background: var(--bs-light, #f8f9fa);
    opacity: 0.85;
}

.class-multi-select__toolbar {
    display: flex;
    flex-wrap: wrap;
    gap: 0.75rem;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 0.85rem;
}

.class-multi-select__search {
    display: flex;
    align-items: stretch;
    flex: 1 1 220px;
    min-width: 200px;
    border: 1px solid var(--bs-border-color, #dee2e6);
    border-radius: 0.5rem;
    overflow: hidden;
    background: #fff;
}

.class-multi-select__search .input-group-text {
    border: 0;
}

.class-multi-select__search .form-control {
    border: 0;
    box-shadow: none;
}

.class-multi-select__actions {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    align-items: center;
}

.class-multi-select__grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
    gap: 0.65rem;
    max-height: 260px;
    overflow-y: auto;
    padding-right: 0.15rem;
}

.class-multi-select__chip {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    width: 100%;
    padding: 0.7rem 0.85rem;
    border: 1px solid var(--bs-border-color, #dee2e6);
    border-radius: 0.65rem;
    background: #fff;
    text-align: right;
    transition: all 0.15s ease;
}

.class-multi-select__chip:hover:not(:disabled) {
    border-color: var(--bs-primary, #0d6efd);
    background: rgba(13, 110, 253, 0.04);
}

.class-multi-select__chip.is-selected {
    border-color: var(--bs-primary, #0d6efd);
    background: rgba(13, 110, 253, 0.08);
    box-shadow: inset 0 0 0 1px rgba(13, 110, 253, 0.15);
}

.class-multi-select__chip:disabled {
    cursor: not-allowed;
}

.class-multi-select__check {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 1.25rem;
    height: 1.25rem;
    border: 1.5px solid var(--bs-border-color, #ced4da);
    border-radius: 0.35rem;
    flex-shrink: 0;
    color: #fff;
    background: #fff;
    font-size: 0.75rem;
}

.class-multi-select__chip.is-selected .class-multi-select__check {
    border-color: var(--bs-primary, #0d6efd);
    background: var(--bs-primary, #0d6efd);
}

.class-multi-select__label {
    font-size: 0.92rem;
    line-height: 1.3;
    color: var(--bs-body-color, #212529);
}

.class-multi-select__empty {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    min-height: 120px;
    color: var(--bs-secondary-color, #6c757d);
    font-size: 0.92rem;
}
</style>
