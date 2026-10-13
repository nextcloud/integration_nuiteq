/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
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
})
