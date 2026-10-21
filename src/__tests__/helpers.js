/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { AxiosError } from 'axios'

/**
 * Put an initial state on the page the way the server does, so the components
 * can read it through the real loadState().
 *
 * @param key the state key, e.g. nuiteq-state
 * @param value what the server would have serialised
 */
export function setInitialState(key, value) {
	const id = `initial-state-integration_nuiteq-${key}`
	document.getElementById(id)?.remove()
	// loadState caches by selector, and every test sets its own state
	delete window._nc_initial_state

	const input = document.createElement('input')
	input.type = 'hidden'
	input.id = id
	input.value = btoa(JSON.stringify(value))
	document.body.appendChild(input)
}

/**
 * The rejection axios hands the components for an answer outside 2xx, built
 * the way its settle() builds it: an Error, marked as axios's, whose message is
 * axios's own English, which no toast should show. What the server wrote is
 * the response's data, parsed when it is JSON, and what the request read,
 * which SendModal still prints; that text is rebuilt here, so for JSON it
 * lacks the server's escaping, which no spec reads.
 *
 * @param status the HTTP status
 * @param data what the server answered: an object for JSON, a string otherwise
 */
export function refusal(status, data) {
	const request = { responseText: typeof data === 'string' ? data : JSON.stringify(data) }
	const response = { status, data, request }
	const code = status >= 400 && status < 500 ? AxiosError.ERR_BAD_REQUEST : AxiosError.ERR_BAD_RESPONSE
	return new AxiosError(`Request failed with status code ${status}`, code, {}, request, response)
}
