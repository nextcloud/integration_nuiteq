/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { AxiosError } from 'axios'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { delay, failureReason, fields, Timer } from '../utils.js'
import { refusal } from './helpers.js'

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
		const error = refusal(400, { error: 'board is locked' })

		expect(failureReason(error, t)).toBe('board is locked')
	})

	it('looks for the reason under error first, then under message', () => {
		expect(failureReason(refusal(400, { error: 'board is locked', message: 'other' }), t)).toBe('board is locked')
		// a blank error is no reason, so the message still counts
		expect(failureReason(refusal(401, { error: '  ', message: 'Current user is not logged in' }), t))
			.toBe('Current user is not logged in')
		// and a blank message is none either
		expect(failureReason(refusal(401, { message: '   ' }), t)).toBe('The server answered 401')
	})

	it('gives the reason Nextcloud itself refused the request with', () => {
		// an expired session, a missing password confirmation and the admin
		// check answer with message rather than error
		const error = refusal(401, { message: 'Current user is not logged in' })

		expect(failureReason(error, t)).toBe('Current user is not logged in')
	})

	it('describes a failure by its status rather than its body', () => {
		const page = '<!DOCTYPE html><p>Exception: OCA\\Nuiteq\\Boom in /var/www/x.php line 42</p>'
		const error = refusal(500, page)

		// the body used to be printed whole, which put an error page, an
		// exception class and a file path into a toast
		expect(failureReason(error, t)).toBe('The server answered 500')
	})

	it('reads the message of an error this app threw itself', () => {
		expect(failureReason(new TypeError('NUITEQ did not answer with a list of boards'), t))
			.toBe('NUITEQ did not answer with a list of boards')
	})

	it('says the server could not be reached when the request never got there', () => {
		// what axios rejects with when the host does not answer. An object
		// shaped like it by hand is not an Error, so it never reached the
		// branch this case takes, and the test could not see it
		const error = new AxiosError('Network Error', AxiosError.ERR_NETWORK, {}, {})
		expect(error instanceof Error).toBe(true)
		expect('response' in error).toBe(false)

		expect(failureReason(error, t)).toBe('The server could not be reached')
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
		const error = refusal(503, { error: { message: 'nope' } })

		expect(failureReason(error, t)).toBe('The server answered 503')
		expect(failureReason(refusal(503, { message: { text: 'nope' } }), t))
			.toBe('The server answered 503')
	})

	it('ignores an error from the server that is only spaces', () => {
		const error = refusal(500, { error: '   ' })

		// shown as the message, it read as a toast ending in a colon with
		// nothing after it, which is the symptom the status answers
		expect(failureReason(error, t)).toBe('The server answered 500')
	})

	it('ignores an empty error from the server', () => {
		expect(failureReason(refusal(503, { error: '' }), t))
			.toBe('The server answered 503')
		// what Nextcloud answers a login it refuses without saying why
		expect(failureReason(refusal(401, { message: '' }), t))
			.toBe('The server answered 401')
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
