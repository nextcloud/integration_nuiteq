/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { config, mount } from '@vue/test-utils'
import axios from 'axios'
import settle from 'axios/unsafe/core/settle.js'
import { describe, expect, it } from 'vitest'
import { refusal } from './helpers.js'

// The translation stub exists to make an untranslatable string visible: a
// string asked for under another app's id resolves in no language, however well
// this app's catalogue carries it. Without these, the stub can be simplified
// away and the defect it catches reopens in silence.
describe('the translation stub the specs run against', () => {
	it('hands back a string asked for under this app', () => {
		expect(window.t('integration_nuiteq', 'Board name')).toBe('Board name')
	})

	it('fills the placeholders of a string asked for under this app', () => {
		expect(window.t('integration_nuiteq', '{name} deleted', { name: 'Retro' }))
			.toBe('Retro deleted')
	})

	it.each([
		['another integration app', 'integration_mattermost'],
		['another Nextcloud app', 'spreed'],
		['this app\'s own PHP namespace', 'nuiteq'],
	])('flags a string asked for under %s', (_, app) => {
		expect(window.t(app, 'Board name')).toContain(`[untranslatable: ${app}]`)
	})

	it('flags one rendered by a template too', () => {
		const wrapper = mount({
			template: '<p>{{ t(\'spreed\', \'Board name\') }} / {{ t(\'integration_nuiteq\', \'Board name\') }}</p>',
		})

		// a template resolves t through the instance proxy, not the page
		// global, so the two halves of the stub have to be the same function
		expect(wrapper.text()).toBe('[untranslatable: spreed] Board name / Board name')
	})

	it('counts through the same stub', () => {
		expect(config.global.mocks.t).toBe(window.t)
		expect(config.global.mocks.n).toBe(window.n)
	})
})

// A toast check is only as good as the rejection it is fed: one whose message
// were empty, say, would stop catching a toast that shows axios's own English.
describe('the refusal the specs reject with', () => {
	it.each([
		[400, { error: 'board is locked' }],
		[500, '<!DOCTYPE html><p>Exception</p>'],
	])('is what axios itself rejects a %i with', async (status, data) => {
		// a real axios whose adapter, standing in for the network, settles the
		// answer the way axios's own adapters do
		const real = await axios.create({
			adapter: (config) => new Promise((resolve, reject) => {
				settle(resolve, reject, { status, statusText: '', headers: {}, config, request: {}, data })
			}),
		}).get('/x').catch((error) => error)
		const built = refusal(status, data)

		expect(Object.getPrototypeOf(built)).toBe(Object.getPrototypeOf(real))
		expect(built.isAxiosError).toBe(true)
		expect(built.message).toBe(real.message)
		expect(built.code).toBe(real.code)
		expect(built.response.status).toBe(real.response.status)
		expect(built.response.data).toEqual(real.response.data)
	})
})
