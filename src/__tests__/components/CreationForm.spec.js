/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { mount } from '@vue/test-utils'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import CreationForm from '../../components/CreationForm.vue'

vi.mock('@nextcloud/dialogs', () => ({ showError: vi.fn(), showSuccess: vi.fn() }))

// a date field, which the shipped field set has commented out: the picker it
// renders was registered under a different spelling than the template asked for
vi.mock('../../utils.js', async (importOriginal) => {
	const actual = await importOriginal()
	return {
		...actual,
		fields: {
			...actual.fields,
			due: { type: 'ncDate', label: 'Due date', placeholder: 'pick a day' },
		},
	}
})

describe('CreationForm', () => {
	let warnings

	beforeEach(() => {
		warnings = []
		vi.spyOn(console, 'warn').mockImplementation((...args) => warnings.push(args.join(' ')))
	})

	afterEach(() => vi.restoreAllMocks())

	it('does not ask Vue to make the field icons reactive', () => {
		mount(CreationForm)

		// the field definitions carry icon components, which Vue warns about
		// being wrapped in a reactive proxy on every mount
		expect(warnings.filter((w) => w.includes('reactive object'))).toEqual([])
	})

	it('asks for a board name', () => {
		const wrapper = mount(CreationForm)

		expect(wrapper.find('#board-name').exists()).toBe(true)
		expect(wrapper.text()).not.toContain('[untranslatable')
		expect(wrapper.text()).toContain('Board name')
	})

	it('renders a picker for a date field', () => {
		const wrapper = mount(CreationForm)

		expect(warnings.filter((w) => w.includes('Failed to resolve component'))).toEqual([])
		expect(wrapper.find('#board-due').exists()).toBe(true)
	})

	it('hides a togglable password until the toggle is on', async () => {
		const wrapper = mount(CreationForm)
		expect(wrapper.find('#board-password').exists()).toBe(false)

		await wrapper.findAll('input[type="checkbox"]')[0].setValue(true)

		expect(wrapper.find('#board-password').exists()).toBe(true)
		expect(wrapper.find('#board-password').attributes('type')).toBe('password')
	})

	it('keeps the password of a new board hidden for the next form', async () => {
		const first = mount(CreationForm)
		await first.findAll('input[type="checkbox"]')[0].setValue(true)
		await first.find('.password-input-wrapper .material-design-icon').trigger('click')
		expect(first.find('#board-password').attributes('type')).toBe('text')
		first.unmount()

		// the toggles used to live on the imported field definitions, which
		// every form shares
		const second = mount(CreationForm)

		expect(second.find('#board-password').exists()).toBe(false)
	})
})
