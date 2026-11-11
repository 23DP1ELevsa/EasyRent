import { computed, onScopeDispose, reactive, toValue, watch } from 'vue'

const pending = reactive(new Set())
const isLoading = computed(() => pending.size > 0)

// Independent tasks receive independent tokens, including overlapping requests.
export function startLoading () {
  const token = Symbol('loading')
  pending.add(token)
  return () => pending.delete(token)
}

export function useLoadingState (...sources) {
  let finish
  const stop = watch(
    () => sources.some(source => Boolean(toValue(source))),
    active => {
      if (active && !finish) {
        finish = startLoading()
      } else if (!active && finish) {
        finish()
        finish = undefined
      }
    },
    { immediate: true, flush: 'sync' },
  )
  onScopeDispose(() => {
    stop()
    finish?.()
  })
}

export function useLoading () {
  return { isLoading }
}
