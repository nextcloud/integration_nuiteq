/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { mount } from '@vue/test-utils'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import BoardDetails from '../../components/BoardDetails.vue'

vi.mock('@nextcloud/axios', () => ({ default: { get: vi.fn(), post: vi.fn(), put: vi.fn() } }))
vi.mock('@nextcloud/dialogs', () => ({ showError: vi.fn(), showSuccess: vi.fn() }))

/**
 * Mount the details of one board.
 *
 * @param board the board to show
 */
function mountDetails(board) {
	return mount(BoardDetails, {
		props: { board, nuiteqUrl: 'https://stage.example.com', talkEnabled: true },
		global: { stubs: { NcModal: { template: '<div><slot /></div>' } } },
	})
}

/**
 * The password as the user sees it.
 *
 * @param wrapper the mounted details
 */
function shownPassword(wrapper) {
	return wrapper.find('#board-password-value').text()
}

/**
 * Click the eye next to the password.
 *
 * @param wrapper the mounted details
 */
async function toggleReveal(wrapper) {
	await wrapper.find('.password-input-wrapper .material-design-icon').trigger('click')
}

describe('BoardDetails', () => {
	beforeEach(() => vi.clearAllMocks())
	afterEach(() => vi.restoreAllMocks())

	it('does not ask Vue to make the field icons reactive', () => {
		const warnings = []
		vi.spyOn(console, 'warn').mockImplementation((...args) => warnings.push(args.join(' ')))

		mountDetails({ id: 'b-1', name: 'Sprint planning', password: 'secret' })

		// the field definitions carry icon components, which Vue warns about
		// being wrapped in a reactive proxy on every mount
		expect(warnings.filter((w) => w.includes('reactive object'))).toEqual([])
	})

	it('names the board', () => {
		const wrapper = mountDetails({ id: 'b-1', name: 'Sprint planning', password: '' })

		expect(wrapper.find('h2').text()).toContain('Sprint planning')
	})

	it('links to the board on the instance', () => {
		const wrapper = mountDetails({ id: 'b-1', name: 'Sprint planning', password: '' })

		expect(wrapper.vm.publicLink).toBe('https://stage.example.com/board/b-1')
	})

	it('hides the password until it is asked for', () => {
		const wrapper = mountDetails({ id: 'b-1', name: 'One', password: 'secret-one' })

		expect(shownPassword(wrapper)).not.toContain('secret-one')
	})

	it('shows the password of the board whose eye was clicked', async () => {
		const wrapper = mountDetails({ id: 'b-1', name: 'One', password: 'secret-one' })

		await toggleReveal(wrapper)

		expect(shownPassword(wrapper)).toContain('secret-one')
	})

	it('keeps the next board\'s password hidden after one was revealed', async () => {
		const first = mountDetails({ id: 'b-1', name: 'One', password: 'secret-one' })
		await toggleReveal(first)
		expect(shownPassword(first)).toContain('secret-one')
		first.unmount()

		// the reveal used to be stored on the imported field definition, which
		// every board and every mount shares
		const second = mountDetails({ id: 'b-2', name: 'Two', password: 'secret-two' })

		expect(shownPassword(second)).not.toContain('secret-two')
	})
})
