import assert from 'node:assert/strict'
import test from 'node:test'
import { effectScope, ref } from 'vue'
import { startLoading, useLoading, useLoadingState } from '../src/stores/loading.js'

const { isLoading } = useLoading()

test('independent tasks keep the screen active until the last task completes', () => {
  const finishFirst = startLoading()
  const finishSecond = startLoading()
  assert.equal(isLoading.value, true)
  finishFirst()
  finishFirst()
  assert.equal(isLoading.value, true)
  finishSecond()
  assert.equal(isLoading.value, false)
})

test('multiple loading flags and reservation IDs share the indicator', () => {
  const scope = effectScope()
  const loading = ref(false)
  const reservationId = ref(null)
  scope.run(() => useLoadingState(loading, () => reservationId.value !== null))
  assert.equal(isLoading.value, false)
  loading.value = true
  reservationId.value = 42
  loading.value = false
  assert.equal(isLoading.value, true)
  reservationId.value = null
  assert.equal(isLoading.value, false)
  scope.stop()
})

test('leaving a loading page releases its token without ending other tasks', () => {
  const scope = effectScope()
  const loading = ref(true)
  scope.run(() => useLoadingState(loading))
  const finishOtherTask = startLoading()
  scope.stop()
  assert.equal(isLoading.value, true)
  finishOtherTask()
  assert.equal(isLoading.value, false)
  loading.value = false
  loading.value = true
  assert.equal(isLoading.value, false)
})

test('completing an asynchronous operation after page disposal is harmless', () => {
  const scope = effectScope()
  const loading = ref(true)
  scope.run(() => useLoadingState(loading))
  scope.stop()
  assert.equal(isLoading.value, false)
  loading.value = false
  assert.equal(isLoading.value, false)
})
