/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { delay, failureReason, fields, Timer } from '../utils.js'

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

describe('failureReason', () => {
	// the harness stub, which flags a string asked for under another app's id
	const t = window.t

	it('prefers what the server said went wrong', () => {
		const error = { response: { status: 400, data: { error: 'board is locked' } } }

		expect(failureReason(error, t)).toBe('board is locked')
	})

	it('describes a failure by its status rather than its body', () => {
		const error = {
			response: {
				status: 500,
				request: { responseText: '<!DOCTYPE html><p>Exception: OCA\\Nuiteq\\Boom in /var/www/x.php line 42</p>' },
			},
		}

		// the body used to be printed whole, which put an error page, an
		// exception class and a file path into a toast
		expect(failureReason(error, t)).toBe('The server answered 500')
	})

	it('reads the message of an error this app threw itself', () => {
		expect(failureReason(new TypeError('NUITEQ did not answer with a list of boards'), t))
			.toBe('NUITEQ did not answer with a list of boards')
	})

	it('says the server could not be reached when there is no answer at all', () => {
		expect(failureReason({ message: 'Network Error' }, t)).toBe('The server could not be reached')
	})

	it('says the same for a rejection carrying nothing', () => {
		// reading .response off it threw, which left the form spinning
		expect(failureReason(undefined, t)).toBe('The server could not be reached')
	})

	it('asks for its own strings under this app', () => {
		// a string asked for under another id is never translated, and a local
		// stub that ignores the id cannot see that
		expect(failureReason({ response: { status: 500 } }, t)).toBe('The server answered 500')
		expect(failureReason(undefined, t)).toBe('The server could not be reached')
	})

	it('reads the message of any error this app threw, not only a TypeError', () => {
		expect(failureReason(new Error('the board list is not a list'), t))
			.toBe('the board list is not a list')
	})

	it('treats a status of zero as no answer', () => {
		expect(failureReason({ response: { status: 0 } }, t)).toBe('The server could not be reached')
	})

	it('ignores an error from the server that is not a string', () => {
		const error = { response: { status: 503, data: { error: { message: 'nope' } } } }

		expect(failureReason(error, t)).toBe('The server answered 503')
	})

	it('ignores an empty error from the server', () => {
		expect(failureReason({ response: { status: 503, data: { error: '' } } }, t))
			.toBe('The server answered 503')
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
