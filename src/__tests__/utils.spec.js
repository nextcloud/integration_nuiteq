/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { delay, fields, Timer } from '../utils.js'

describe('Timer', () => {
	beforeEach(() => vi.useFakeTimers())
	afterEach(() => vi.useRealTimers())

	it('calls back once the time has passed', () => {
		const callback = vi.fn()
		new Timer(callback, 10000)

		vi.advanceTimersByTime(9999)
		expect(callback).not.toHaveBeenCalled()

		vi.advanceTimersByTime(1)
		expect(callback).toHaveBeenCalledOnce()
	})

	it('never calls back once paused, which is how an undone deletion is cancelled', () => {
		const callback = vi.fn()
		const timer = new Timer(callback, 10000)

		vi.advanceTimersByTime(3000)
		timer.pause()
		vi.advanceTimersByTime(60000)

		expect(callback).not.toHaveBeenCalled()
	})

	it('waits only for what was left of the time when resumed', () => {
		const callback = vi.fn()
		const timer = new Timer(callback, 10000)

		vi.advanceTimersByTime(4000)
		timer.pause()
		vi.advanceTimersByTime(60000)
		timer.resume()

		vi.advanceTimersByTime(5999)
		expect(callback).not.toHaveBeenCalled()

		vi.advanceTimersByTime(1)
		expect(callback).toHaveBeenCalledOnce()
	})
})

describe('delay', () => {
	beforeEach(() => vi.useFakeTimers())
	afterEach(() => vi.useRealTimers())

	it('calls back only once the time has passed', () => {
		const callback = vi.fn()
		delay(callback, 200)()

		vi.advanceTimersByTime(199)
		expect(callback).not.toHaveBeenCalled()

		vi.advanceTimersByTime(1)
		expect(callback).toHaveBeenCalledOnce()
	})

	it('keeps only the last call of a burst', () => {
		const callback = vi.fn()
		const delayed = delay(callback, 100)

		delayed('first')
		vi.advanceTimersByTime(50)
		delayed('second')
		vi.advanceTimersByTime(100)

		expect(callback).toHaveBeenCalledOnce()
		expect(callback).toHaveBeenCalledWith('second')
	})

	it('treats a missing delay as no delay', () => {
		const callback = vi.fn()
		delay(callback)()

		vi.advanceTimersByTime(0)

		expect(callback).toHaveBeenCalledOnce()
	})
})

describe('the board fields the forms are built from', () => {
	it('describes every field they render', () => {
		for (const [key, field] of Object.entries(fields)) {
			expect(field.type, key).toBeTruthy()
			expect(typeof field.label, key).toBe('string')
			expect(field.label, key).toBeTruthy()
		}
	})

	it('asks for a board name and offers one', () => {
		expect(fields.name.mandatory).toBe(true)
		expect(fields.name.default).toBeTruthy()
		expect(fields.name.type).toBe('text')
	})
})
