import { reactive } from 'vue'

const state = reactive({
  visible: false,
  text: '',
  color: 'info',
  timeout: 3200,
  queue: [],
})

function applyNotification(notification) {
  state.text = notification.text
  state.color = notification.color
  state.timeout = notification.timeout
  state.visible = true
}

function enqueue(text, color = 'info', timeout = 3200) {
  const normalizedText = String(text || '').trim()
  if (!normalizedText) return

  const notification = {
    text: normalizedText,
    color,
    timeout,
  }

  if (state.visible) {
    state.queue.push(notification)
    return
  }

  applyNotification(notification)
}

function showNext() {
  if (state.visible) return

  const nextNotification = state.queue.shift()
  if (!nextNotification) return

  applyNotification(nextNotification)
}

function setVisible(value) {
  state.visible = value
  if (!value) {
    window.setTimeout(showNext, 120)
  }
}

export function useNotifications() {
  return {
    notificationState: state,
    setNotificationVisible: setVisible,
    notify: enqueue,
    notifySuccess: (text, timeout) => enqueue(text, 'success', timeout),
    notifyError: (text, timeout) => enqueue(text, 'error', timeout),
    notifyInfo: (text, timeout) => enqueue(text, 'info', timeout),
    notifyWarning: (text, timeout) => enqueue(text, 'warning', timeout),
  }
}