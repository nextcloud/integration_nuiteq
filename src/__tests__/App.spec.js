/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import axios from '@nextcloud/axios'
import { showError, showUndo } from '@nextcloud/dialogs'
import { flushPromises, mount } from '@vue/test-utils'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import App from '../App.vue'
import { setInitialState } from './helpers.js'

vi.mock('@nextcloud/axios', () => ({ default: { get: vi.fn(), post: vi.fn(), put: vi.fn() } }))
vi.mock('@nextcloud/dialogs', () => ({
	showError: vi.fn(),
	showSuccess: vi.fn(),
	showUndo: vi.fn(),
}))

/**
 * A board as the server lists it.
 *
 * @param over what to add or override
 */
function board(over = {}) {
	return { id: 'b-1', name: 'Sprint planning', ...over }
}

/**
 * Mount the app with the state the server put on the page. The app shell reads
 * library state that has nothing to do with the app's own behaviour, so only
 * the shell is stubbed; everything inside it renders for real.
 *
 * @param state what to add or override
 */
function mountApp(state = {}) {
	setInitialState('nuiteq-state', {
		base_url: 'https://stage.example.com',
		user_name: 'jane',
		api_key: 'a-key',
		talk_enabled: true,
		board_list: [board()],
		...state,
	})
	const slotOnly = { template: '<div><slot /></div>' }
	return mount(App, {
		global: { stubs: { NcContent: slotOnly, NcAppContent: slotOnly, NcModal: slotOnly } },
	})
}

describe('the board list', () => {
	beforeEach(() => {
		vi.clearAllMocks()
		axios.post.mockResolvedValue({ data: {} })
	})

	afterEach(() => vi.useRealTimers())

	it('names every board in the navigation', () => {
		const wrapper = mountApp({ board_list: [board(), board({ id: 'b-2', name: 'Retro' })] })

		// the entries used to render blank, with the name only as a tooltip
		expect(wrapper.findAll('.app-navigation-entry-link').map((entry) => entry.text()))
			.toEqual(['Sprint planning', 'Retro'])
	})

	it('offers to undo a deletion before carrying it out', async () => {
		vi.useFakeTimers()
		const wrapper = mountApp()

		wrapper.vm.onBoardDeleted('b-1')
		await flushPromises()

		expect(showUndo).toHaveBeenCalledOnce()
		expect(showUndo.mock.calls[0][0]).toContain('Sprint planning')
		expect(axios.post).not.toHaveBeenCalled()
		expect(wrapper.vm.activeBoardCount).toBe(0)
	})

	it('deletes the board once the undo window has passed', async () => {
		vi.useFakeTimers()
		const wrapper = mountApp()

		wrapper.vm.onBoardDeleted('b-1')
		await vi.advanceTimersByTimeAsync(10000)

		expect(axios.post).toHaveBeenCalledWith(
			'/index.php/apps/integration_nuiteq/delete',
			{ boardId: 'b-1' },
		)
		expect(wrapper.vm.activeBoardCount).toBe(0)
	})

	it('keeps the board when the user undoes the deletion', async () => {
		vi.useFakeTimers()
		const wrapper = mountApp()

		wrapper.vm.onBoardDeleted('b-1')
		showUndo.mock.calls[0][1]()
		await vi.advanceTimersByTimeAsync(60000)

		expect(axios.post).not.toHaveBeenCalled()
		expect(wrapper.vm.activeBoardCount).toBe(1)
	})

	it('ignores an undo pressed after the deletion already went out', async () => {
		vi.useFakeTimers()
		const wrapper = mountApp()

		wrapper.vm.onBoardDeleted('b-1')
		await vi.advanceTimersByTimeAsync(10000)
		await flushPromises()
		// the toast used to live exactly as long as the deletion timer, so this
		// click could put back a board NUITEQ had already deleted
		showUndo.mock.calls[0][1]()
		await flushPromises()

		expect(axios.post).toHaveBeenCalledOnce()
		expect(wrapper.vm.activeBoardCount).toBe(0)
	})

	it('closes the undo offer before the deletion goes out', () => {
		vi.useFakeTimers()
		const wrapper = mountApp()

		wrapper.vm.onBoardDeleted('b-1')

		expect(showUndo.mock.calls[0][2].timeout).toBeLessThan(10000)
	})

	it('does nothing for a board it does not have', async () => {
		vi.useFakeTimers()
		const wrapper = mountApp()

		wrapper.vm.onBoardDeleted('b-does-not-exist')
		await vi.advanceTimersByTimeAsync(60000)

		expect(showUndo).not.toHaveBeenCalled()
		expect(axios.post).not.toHaveBeenCalled()
		expect(wrapper.vm.activeBoardCount).toBe(1)
	})

	it('replaces the board list it is given rather than adding to it', async () => {
		const wrapper = mountApp()
		axios.get.mockResolvedValue({ data: [board({ id: 'b-9', name: 'From the server' })] })

		await wrapper.vm.getBoards()
		await flushPromises()

		expect(wrapper.vm.state.board_list.map((b) => b.id)).toEqual(['b-9'])
	})

	it('shows an empty list when the server sent something that is not one', async () => {
		const wrapper = mountApp({ board_list: { error: 'Server error: 503' } })

		expect(wrapper.vm.activeBoards).toEqual([])
		expect(wrapper.text()).toContain('Create a board')
	})

	it('puts the board back when the server refused to delete it', async () => {
		vi.useFakeTimers()
		axios.post.mockRejectedValue({ response: { data: { error: 'board is locked' } } })
		const wrapper = mountApp()

		wrapper.vm.onBoardDeleted('b-1')
		await vi.advanceTimersByTimeAsync(10000)
		await flushPromises()

		// the board is hidden before the request: a refusal must not leave it
		// hidden, or the user loses sight of a board that still exists
		expect(showError).toHaveBeenCalled()
		expect(wrapper.vm.activeBoardCount).toBe(1)
	})
})
