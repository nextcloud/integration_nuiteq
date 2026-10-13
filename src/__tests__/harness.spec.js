/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { config, mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'

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
