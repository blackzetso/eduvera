import { computed, onUnmounted, ref } from 'vue'
import axios from 'axios'

/**
 * Browser speech-to-text for Dova voice notes.
 * Primary: Web Speech API. Fallback: Whisper via server (when browser STT unavailable).
 *
 * Mobile QA notes:
 * - Android Chrome: Web Speech + getUserMedia; sessions may end early (auto-restart while recording).
 * - iPhone/iPad Safari: requires mic permission; recognition restarts on onend; use Stop to finish.
 * - Permission denied → mic_blocked. No hardware → mic_unavailable. Silence → recognition_timeout.
 */
export function useDovaSpeechRecognition({ locale, whisperFallback, currentPath }) {
  const isRecording = ref(false)
  const isProcessing = ref(false)
  const recordingSeconds = ref(0)
  const error = ref(null)
  const lastDetectedLanguage = ref(null)

  let recognition = null
  let mediaRecorder = null
  let mediaStream = null
  let timerInterval = null
  let audioChunks = []
  let accumulatedTranscript = ''
  let useWebSpeech = false
  let stopping = false
  let silenceTimeout = null
  const SILENCE_TIMEOUT_MS = 12000

  const SpeechRecognition = typeof window !== 'undefined'
    ? (window.SpeechRecognition || window.webkitSpeechRecognition)
    : null

  const webSpeechAvailable = computed(() => Boolean(SpeechRecognition))

  const timerLabel = computed(() => {
    const minutes = Math.floor(recordingSeconds.value / 60)
    const seconds = recordingSeconds.value % 60
    return `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`
  })

  const errorMessages = computed(() => ({
    mic_blocked: {
      ar: 'تم حظر الميكروفون. يرجى السماح بالوصول من إعدادات المتصفح.',
      en: 'Microphone blocked. Please allow access in your browser settings.',
    },
    mic_unavailable: {
      ar: 'الميكروفون غير متاح على هذا الجهاز.',
      en: 'Microphone unavailable on this device.',
    },
    recognition_failed: {
      ar: 'تعذّر التعرف على الصوت. حاول مرة أخرى أو اكتب سؤالك.',
      en: 'Recognition failed. Try again or type your question.',
    },
    recognition_timeout: {
      ar: 'لم أسمع صوتاً. حاول التحدث مرة أخرى أو اكتب سؤالك.',
      en: "I didn't hear anything. Try speaking again or type your question.",
    },
    whisper_unavailable: {
      ar: 'التعرف الصوتي غير متاح حالياً. يرجى الكتابة.',
      en: 'Voice recognition is unavailable. Please type your question.',
    },
  }))

  const errorMessage = computed(() => {
    if (!error.value) {
      return ''
    }

    const messages = errorMessages.value[error.value] ?? errorMessages.value.recognition_failed
    const lang = locale.value === 'ar' ? 'ar' : 'en'

    return messages[lang]
  })

  /**
   * Egyptian Arabic (ar-EG) improves dialect recognition for phrases like
   * "عايز أعرف المصروفات", "فين التقديم", "ازاي أسجل ابني".
   * Mobile: iOS Safari may end sessions early — auto-restart while recording.
   */
  function speechLocale() {
    return locale.value === 'ar' ? 'ar-EG' : 'en-US'
  }

  function clearSilenceTimeout() {
    if (silenceTimeout) {
      clearTimeout(silenceTimeout)
      silenceTimeout = null
    }
  }

  function armSilenceTimeout() {
    clearSilenceTimeout()
    silenceTimeout = setTimeout(() => {
      if (isRecording.value && !stopping && !accumulatedTranscript.trim()) {
        error.value = 'recognition_timeout'
        cancelRecording()
        logRecognition({
          success: false,
          engine: useWebSpeech ? 'web_speech' : 'whisper',
          error_code: 'recognition_timeout',
          duration_ms: recordingSeconds.value * 1000,
        })
      }
    }, SILENCE_TIMEOUT_MS)
  }

  function detectLanguage(text) {
    if (/[\u0600-\u06FF]/.test(text)) {
      return 'ar'
    }

    if (/[a-zA-Z]/.test(text)) {
      return 'en'
    }

    return locale.value === 'ar' ? 'ar' : 'en'
  }

  function startTimer() {
    recordingSeconds.value = 0
    stopTimer()
    timerInterval = setInterval(() => {
      recordingSeconds.value += 1
      if (recordingSeconds.value >= 120) {
        stopRecording()
      }
    }, 1000)
  }

  function stopTimer() {
    if (timerInterval) {
      clearInterval(timerInterval)
      timerInterval = null
    }
  }

  function releaseStream() {
    if (mediaStream) {
      mediaStream.getTracks().forEach((track) => track.stop())
      mediaStream = null
    }
  }

  function resetRecognition() {
    if (recognition) {
      try {
        recognition.onresult = null
        recognition.onerror = null
        recognition.onend = null
        recognition.abort()
      } catch {
        // Ignore cleanup errors.
      }
      recognition = null
    }

    mediaRecorder = null
    audioChunks = []
    accumulatedTranscript = ''
    stopping = false
    clearSilenceTimeout()
    releaseStream()
  }

  async function logRecognition(payload) {
    try {
      await axios.post(route('dova.copilot.voice.recognition'), {
        ...payload,
        path: currentPath(),
      })
    } catch {
      // Analytics must not break UX.
    }
  }

  async function requestMicrophone() {
    if (!navigator.mediaDevices?.getUserMedia) {
      error.value = 'mic_unavailable'
      return false
    }

    try {
      mediaStream = await navigator.mediaDevices.getUserMedia({ audio: true })
      return true
    } catch (err) {
      if (err?.name === 'NotAllowedError' || err?.name === 'PermissionDeniedError') {
        error.value = 'mic_blocked'
      } else {
        error.value = 'mic_unavailable'
      }

      return false
    }
  }

  async function startWebSpeech() {
    accumulatedTranscript = ''
    stopping = false
    recognition = new SpeechRecognition()
    recognition.lang = speechLocale()
    recognition.interimResults = true
    recognition.continuous = true
    recognition.maxAlternatives = 3

    recognition.onresult = (event) => {
      let interim = ''
      for (let i = event.resultIndex; i < event.results.length; i += 1) {
        const piece = event.results[i][0]?.transcript ?? ''
        if (event.results[i].isFinal) {
          accumulatedTranscript += piece
        } else {
          interim += piece
        }
      }

      if (interim) {
        accumulatedTranscript = `${accumulatedTranscript}${interim}`.trim()
      }

      if (accumulatedTranscript.trim()) {
        clearSilenceTimeout()
      }
    }

    recognition.onerror = (event) => {
      if (event.error === 'not-allowed' || event.error === 'service-not-allowed') {
        error.value = 'mic_blocked'
      } else if (event.error === 'no-speech' && !accumulatedTranscript.trim()) {
        error.value = 'recognition_timeout'
      } else if (event.error !== 'aborted' && event.error !== 'no-speech') {
        error.value = 'recognition_failed'
      }
    }

    recognition.onend = () => {
      if (isRecording.value && !stopping) {
        try {
          recognition.start()
        } catch {
          // iOS/Android may throttle restarts — next user action recovers.
        }
      }
    }

    recognition.start()
    armSilenceTimeout()
  }

  function startMediaRecorder() {
    if (!mediaStream) {
      return false
    }

    audioChunks = []
    const mimeType = MediaRecorder.isTypeSupported('audio/webm;codecs=opus')
      ? 'audio/webm;codecs=opus'
      : (MediaRecorder.isTypeSupported('audio/webm') ? 'audio/webm' : '')

    mediaRecorder = mimeType
      ? new MediaRecorder(mediaStream, { mimeType })
      : new MediaRecorder(mediaStream)

    mediaRecorder.ondataavailable = (event) => {
      if (event.data?.size > 0) {
        audioChunks.push(event.data)
      }
    }

    mediaRecorder.start(250)
    return true
  }

  async function transcribeWithWhisper(durationMs) {
    if (!whisperFallback?.value) {
      error.value = 'whisper_unavailable'
      await logRecognition({
        success: false,
        engine: 'whisper',
        error_code: 'whisper_unavailable',
        duration_ms: durationMs,
      })
      return null
    }

    const blob = new Blob(audioChunks, { type: audioChunks[0]?.type || 'audio/webm' })
    if (!blob.size) {
      error.value = 'recognition_failed'
      await logRecognition({
        success: false,
        engine: 'whisper',
        error_code: 'empty_audio',
        duration_ms: durationMs,
      })
      return null
    }

    const form = new FormData()
    form.append('audio', blob, 'voice-note.webm')
    form.append('path', currentPath())
    form.append('hint_language', locale.value === 'ar' ? 'ar' : 'en')
    form.append('dialect', locale.value === 'ar' ? 'egyptian' : '')
    form.append('duration_ms', String(durationMs))

    try {
      const { data } = await axios.post(route('dova.copilot.voice.transcribe'), form, {
        headers: { 'Content-Type': 'multipart/form-data' },
      })

      if (data?.success && data.transcript) {
        lastDetectedLanguage.value = data.detected_language ?? detectLanguage(data.transcript)
        return {
          transcript: data.transcript,
          detectedLanguage: lastDetectedLanguage.value,
        }
      }

      error.value = data?.error === 'whisper_unavailable' ? 'whisper_unavailable' : 'recognition_failed'
      return null
    } catch {
      error.value = 'recognition_failed'
      return null
    }
  }

  async function startRecording() {
    clearError()

    if (!webSpeechAvailable.value && !whisperFallback?.value) {
      error.value = 'whisper_unavailable'
      return false
    }

    const micOk = await requestMicrophone()
    if (!micOk) {
      return false
    }

    useWebSpeech = webSpeechAvailable.value
    isRecording.value = true
    startTimer()

    if (useWebSpeech) {
      try {
        await startWebSpeech()
      } catch {
        error.value = 'recognition_failed'
        cancelRecording()
        return false
      }
    } else {
      const started = startMediaRecorder()
      if (!started) {
        error.value = 'mic_unavailable'
        cancelRecording()
        return false
      }
    }

    return true
  }

  async function stopRecording() {
    if (!isRecording.value) {
      return null
    }

    isProcessing.value = true
    const durationMs = recordingSeconds.value * 1000
    stopTimer()

    if (useWebSpeech && recognition) {
      stopping = true
      clearSilenceTimeout()

      return new Promise((resolve) => {
        recognition.onend = async () => {
          isRecording.value = false
          const transcript = accumulatedTranscript.trim()

          if (transcript) {
            const detectedLanguage = detectLanguage(transcript)
            lastDetectedLanguage.value = detectedLanguage
            await logRecognition({
              success: true,
              engine: 'web_speech',
              transcript,
              detected_language: detectedLanguage,
              duration_ms: durationMs,
            })
            resetRecognition()
            isProcessing.value = false
            resolve({ transcript, detectedLanguage })
            return
          }

          error.value = 'recognition_failed'
          await logRecognition({
            success: false,
            engine: 'web_speech',
            error_code: 'empty_transcript',
            duration_ms: durationMs,
          })
          resetRecognition()
          isProcessing.value = false
          resolve(null)
        }

        try {
          recognition.stop()
        } catch {
          resetRecognition()
          isProcessing.value = false
          error.value = 'recognition_failed'
          resolve(null)
        }
      })
    }

    if (mediaRecorder && mediaRecorder.state !== 'inactive') {
      return new Promise((resolve) => {
        mediaRecorder.onstop = async () => {
          isRecording.value = false
          const result = await transcribeWithWhisper(durationMs)
          resetRecognition()
          isProcessing.value = false
          resolve(result)
        }

        mediaRecorder.stop()
      })
    }

    isRecording.value = false
    isProcessing.value = false
    resetRecognition()
    return null
  }

  function cancelRecording() {
    stopping = true
    clearSilenceTimeout()
    stopTimer()
    isRecording.value = false
    isProcessing.value = false

    if (recognition) {
      try {
        recognition.abort()
      } catch {
        // Ignore abort errors.
      }
    }

    if (mediaRecorder && mediaRecorder.state !== 'inactive') {
      try {
        mediaRecorder.stop()
      } catch {
        // Ignore stop errors.
      }
    }

    resetRecognition()
  }

  function clearError() {
    error.value = null
  }

  onUnmounted(() => {
    cancelRecording()
  })

  return {
    isRecording,
    isProcessing,
    recordingSeconds,
    timerLabel,
    error,
    errorMessage,
    lastDetectedLanguage,
    webSpeechAvailable,
    startRecording,
    stopRecording,
    cancelRecording,
    clearError,
  }
}
