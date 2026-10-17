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

	it('keeps the next board\'s password hidden when the user switches boards', async () => {
		const wrapper = mountApp({
			board_list: [
				// one name for both: the key has to be the id
				board({ id: 'b-1', name: 'Planning', password: 'secret-one' }),
				board({ id: 'b-2', name: 'Planning', password: 'secret-two' }),
			],
		})

		const entries = () => wrapper.findAll('.app-navigation-entry-link')
		await entries()[0].trigger('click')
		await wrapper.find('.password-input-wrapper .material-design-icon').trigger('click')
		expect(wrapper.find('#board-password-value').text()).toContain('secret-one')

		await entries()[1].trigger('click')

		// the details used to be one reused instance, so everything it held
		// about the first board carried over to the second
		expect(wrapper.vm.selectedBoardId).toBe('b-2')
		expect(wrapper.find('#board-password-value').text()).not.toContain('secret-two')
	})

	it('keeps a renamed board on the same navigation row', async () => {
		const wrapper = mountApp({ board_list: [board(), board({ id: 'b-2', name: 'Retro' })] })
		const row = wrapper.findAll('.boardItem')[0].element

		wrapper.vm.state.board_list[0].name = 'Planning'
		await flushPromises()

		// a board is its id: keying the list on the name tears the row down
		// and builds another one whenever a board is renamed
		expect(wrapper.findAll('.boardItem')[0].element).toBe(row)
		expect(wrapper.findAll('.boardItem')[0].text()).toContain('Planning')
	})

	it('settles the reload before it hands back', async () => {
		const wrapper = mountApp({ board_list: [] })
		// two microtasks deep, so awaiting nothing is not enough
		axios.get.mockReturnValue(Promise.resolve().then(() => Promise.resolve({ data: [board({ id: 'b-9', name: 'Retro' })] })))

		await wrapper.vm.getBoards()

		expect(wrapper.vm.state.board_list.map((b) => b.name)).toEqual(['Retro'])
	})

	it('settles the creation before it hands back', async () => {
		axios.post.mockReturnValue(Promise.resolve().then(() => Promise.resolve({ data: { id: 'b-9' } })))
		const wrapper = mountApp({ board_list: [] })

		await wrapper.vm.onCreationValidate({ name: 'Retro', password: '' })

		expect(wrapper.vm.selectedBoardId).toBe('b-9')
	})

	it('keeps the boards it already had when a new one is created', async () => {
		axios.post.mockResolvedValue({ data: { id: 'b-9' } })
		const wrapper = mountApp({ board_list: [board(), board({ id: 'b-2', name: 'Retro' })] })

		await wrapper.vm.onCreationValidate({ name: 'Planning', password: '' })
		await flushPromises()

		// every creation test started from an empty list, so keeping the
		// boards that were there and discarding them were the same thing
		expect(wrapper.vm.activeBoards.map((b) => b.name))
			.toEqual(['Sprint planning', 'Retro', 'Planning'])
	})

	it('keeps the board objects a pending deletion holds on to', async () => {
		axios.post.mockResolvedValue({ data: { id: 'b-9' } })
		const wrapper = mountApp({ board_list: [board()] })
		const existing = wrapper.vm.state.board_list[0]

		await wrapper.vm.onCreationValidate({ name: 'Planning', password: '' })
		await flushPromises()

		// rebuilding the list must not replace the objects a pending deletion
		// and its undo have already captured
		expect(wrapper.vm.state.board_list[0]).toBe(existing)
	})

	it('keeps a board NUITEQ created when the list is not one', async () => {
		axios.post.mockResolvedValue({ data: { id: 'b-9' } })
		const wrapper = mountApp({ board_list: { error: 'Server error: 503' } })

		await wrapper.vm.onCreationValidate({ name: 'Retro', password: '' })
		await flushPromises()

		// pushing onto it threw, and the board NUITEQ had created was lost
		expect(wrapper.vm.activeBoards.map((b) => b.name)).toEqual(['Retro'])
		expect(showError).not.toHaveBeenCalled()
	})

	it('marks the open board in the navigation', async () => {
		const wrapper = mountApp({ board_list: [board(), board({ id: 'b-2', name: 'Retro' })] })
		expect(wrapper.findAll('.app-navigation-entry.active')).toHaveLength(0)

		await wrapper.findAll('.app-navigation-entry-link')[1].trigger('click')

		const rows = wrapper.findAll('.boardItem')
		expect(rows[1].find('.app-navigation-entry.active').exists()).toBe(true)
		expect(rows[0].find('.app-navigation-entry.active').exists()).toBe(false)
		// the open board also carries the filled icon, and the unselected ones
		// the outline
		expect(rows[1].find('.clipboard-edit-icon').exists()).toBe(true)
		expect(rows[0].find('.clipboard-edit-outline-icon').exists()).toBe(true)
		// and it says so to a screen reader, which a class cannot
		expect(rows[1].find('[aria-current="page"]').exists()).toBe(true)
		expect(rows[0].find('[aria-current]').exists()).toBe(false)
	})

	describe('creating a board', () => {
		it('opens the board NUITEQ created', async () => {
			axios.post.mockResolvedValue({ data: { id: 'b-9' } })
			const wrapper = mountApp({ board_list: [] })
			wrapper.vm.creationModalOpen = true

			await wrapper.vm.onCreationValidate({ name: 'Retro', password: '' })
			await flushPromises()

			expect(wrapper.vm.selectedBoardId).toBe('b-9')
			expect(wrapper.vm.activeBoards.map((b) => b.name)).toEqual(['Retro'])
			expect(wrapper.vm.creationModalOpen).toBe(false)
		})

		it.each([
			['no id at all', { status: 'ok' }],
			['an empty id', { id: '' }],
			['an id that is not a string', { id: 42 }],
		])('does not add a board NUITEQ answered with %s', async (_, data) => {
			axios.post.mockResolvedValue({ data })
			const wrapper = mountApp({ board_list: [] })

			await wrapper.vm.onCreationValidate({ name: 'Retro', password: '' })
			await flushPromises()

			// the entry used to be added with an undefined id, which left it in
			// the navigation unopenable
			expect(wrapper.vm.activeBoards).toEqual([])
			expect(wrapper.vm.selectedBoard).toBeFalsy()
			expect(showError).toHaveBeenCalledWith('Failed to create new board: NUITEQ did not say which board it created')
		})

		it('says why a board could not be created', async () => {
			axios.post.mockRejectedValue({ response: { data: { error: 'name already taken' } } })
			const wrapper = mountApp({ board_list: [] })
			wrapper.vm.creationModalOpen = true

			await wrapper.vm.onCreationValidate({ name: 'Retro', password: '' })
			await flushPromises()

			expect(wrapper.vm.activeBoards).toEqual([])
			expect(showError).toHaveBeenCalledWith('Failed to create new board: name already taken')
			// closing it would lose what the user typed
			expect(wrapper.vm.creationModalOpen).toBe(true)
		})

		it('stops showing itself as busy either way', async () => {
			axios.post.mockRejectedValue({ response: { data: { error: 'name already taken' } } })
			const wrapper = mountApp({ board_list: [] })

			await wrapper.vm.onCreationValidate({ name: 'Retro', password: '' })
			await flushPromises()

			expect(wrapper.vm.creating).toBe(false)
		})
	})

	it('starts a deletion from the action in the board\'s row', async () => {
		vi.useFakeTimers()
		const wrapper = mountApp({ board_list: [board(), board({ id: 'b-2', name: 'Retro' })] })
		wrapper.vm.selectedBoardId = 'b-1'

		// the row's menu does not open in jsdom, so go through the component
		// that owns the action rather than the handler it ends up calling
		const row = wrapper.findAllComponents({ name: 'BoardNavigationItem' })[1]
		row.vm.onDeleteClick()
		await flushPromises()

		expect(showUndo).toHaveBeenCalledWith('Retro deleted', expect.any(Function))
		expect(wrapper.vm.activeBoards.map((b) => b.name)).toEqual(['Sprint planning'])
		// a deletion of another board leaves the open one open
		expect(wrapper.vm.selectedBoardId).toBe('b-1')
	})

	it('opens a board from the row the user clicks', async () => {
		const wrapper = mountApp({ board_list: [board(), board({ id: 'b-2', name: 'Retro' })] })

		const row = wrapper.findAllComponents({ name: 'BoardNavigationItem' })[1]
		row.vm.onBoardClick()
		await flushPromises()

		expect(wrapper.vm.selectedBoardId).toBe('b-2')
	})

	it('closes the board it is deleting', async () => {
		vi.useFakeTimers()
		const wrapper = mountApp({ board_list: [board(), board({ id: 'b-2', name: 'Retro' })] })
		wrapper.vm.selectedBoardId = 'b-1'

		wrapper.vm.onBoardDeleted('b-1')

		expect(wrapper.vm.selectedBoardId).toBe('')
	})

	it('leaves the user on their board while another one is being deleted', async () => {
		vi.useFakeTimers()
		const wrapper = mountApp({ board_list: [board(), board({ id: 'b-2', name: 'Retro' })] })
		wrapper.vm.selectedBoardId = 'b-1'

		wrapper.vm.onBoardDeleted('b-2')

		expect(wrapper.vm.selectedBoardId).toBe('b-1')
	})

	it('puts the user back on the board the server refused to delete', async () => {
		vi.useFakeTimers()
		axios.post.mockRejectedValue({ response: { data: { error: 'board is locked' } } })
		const wrapper = mountApp({ board_list: [board(), board({ id: 'b-2', name: 'Retro' })] })
		wrapper.vm.selectedBoardId = 'b-1'

		wrapper.vm.onBoardDeleted('b-1')
		await vi.advanceTimersByTimeAsync(10000)
		await flushPromises()

		expect(wrapper.vm.selectedBoardId).toBe('b-1')
		expect(wrapper.vm.activeBoardCount).toBe(2)
		expect(showError).toHaveBeenCalledWith('Failed to delete the board: board is locked')
	})

	it('does not move the user onto a board they were not looking at', async () => {
		vi.useFakeTimers()
		axios.post.mockRejectedValue({ response: { data: { error: 'board is locked' } } })
		const wrapper = mountApp({ board_list: [board(), board({ id: 'b-2', name: 'Retro' })] })
		wrapper.vm.selectedBoardId = 'b-1'

		// a refused deletion of another board restored the selection all the
		// same, which moved the user off the board they were reading
		wrapper.vm.onBoardDeleted('b-2')
		await vi.advanceTimersByTimeAsync(10000)
		await flushPromises()

		expect(wrapper.vm.selectedBoardId).toBe('b-1')
	})

	it('does not open a board the user never opened when its deletion is refused', async () => {
		vi.useFakeTimers()
		axios.post.mockRejectedValue({ response: { data: { error: 'board is locked' } } })
		const wrapper = mountApp({ board_list: [board(), board({ id: 'b-2', name: 'Retro' })] })

		// nothing is open at page load, and delete is offered on every row, so
		// restoring a selection that was never made opens a board by itself
		expect(wrapper.vm.selectedBoardId).toBe('')
		wrapper.vm.onBoardDeleted('b-2')
		await vi.advanceTimersByTimeAsync(10000)
		await flushPromises()

		expect(wrapper.vm.selectedBoardId).toBe('')
	})

	it('puts the user back on the board they undid the deletion of', async () => {
		vi.useFakeTimers()
		const wrapper = mountApp({ board_list: [board(), board({ id: 'b-2', name: 'Retro' })] })
		wrapper.vm.selectedBoardId = 'b-1'

		wrapper.vm.onBoardDeleted('b-1')
		expect(wrapper.vm.selectedBoardId).toBe('')
		showUndo.mock.calls.at(-1)[1]()
		await flushPromises()

		// the board came back to the list but the user was left looking at
		// "no selected board" beside it
		expect(wrapper.vm.selectedBoardId).toBe('b-1')
		expect(wrapper.vm.activeBoardCount).toBe(2)
	})

	it('does not open a board the user never opened when a deletion is undone', async () => {
		vi.useFakeTimers()
		const wrapper = mountApp({ board_list: [board(), board({ id: 'b-2', name: 'Retro' })] })

		expect(wrapper.vm.selectedBoardId).toBe('')
		wrapper.vm.onBoardDeleted('b-2')
		showUndo.mock.calls.at(-1)[1]()
		await flushPromises()

		expect(wrapper.vm.selectedBoardId).toBe('')
	})

	it('leaves the user on the board they opened before undoing another deletion', async () => {
		vi.useFakeTimers()
		const wrapper = mountApp({ board_list: [board(), board({ id: 'b-2', name: 'Retro' })] })
		wrapper.vm.selectedBoardId = 'b-1'

		wrapper.vm.onBoardDeleted('b-1')
		wrapper.vm.selectedBoardId = 'b-2'
		showUndo.mock.calls.at(-1)[1]()
		await flushPromises()

		// the board comes back, but the user has moved on since
		expect(wrapper.vm.selectedBoardId).toBe('b-2')
		expect(wrapper.vm.activeBoardCount).toBe(2)
	})

	it('leaves the user on the board they opened during the undo window', async () => {
		vi.useFakeTimers()
		axios.post.mockRejectedValue({ response: { data: { error: 'board is locked' } } })
		const wrapper = mountApp({ board_list: [board(), board({ id: 'b-2', name: 'Retro' })] })
		wrapper.vm.selectedBoardId = 'b-1'

		wrapper.vm.onBoardDeleted('b-1')
		wrapper.vm.selectedBoardId = 'b-2'
		await vi.advanceTimersByTimeAsync(10000)
		await flushPromises()

		expect(wrapper.vm.selectedBoardId).toBe('b-2')
	})

	it('waits ten seconds before deleting, which is how long the undo offer lasts', async () => {
		vi.useFakeTimers()
		axios.post.mockResolvedValue({ data: '' })
		const wrapper = mountApp()

		wrapper.vm.onBoardDeleted('b-1')
		await vi.advanceTimersByTimeAsync(9999)
		expect(axios.post).not.toHaveBeenCalled()

		// a shorter window deletes the board while the toast still offers undo
		await vi.advanceTimersByTimeAsync(1)
		expect(axios.post).toHaveBeenCalledOnce()
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

	it('keeps the boards and says so when a reload answers with something that is not a list', async () => {
		const wrapper = mountApp({ board_list: [board(), board({ id: 'b-2', name: 'Retro' })] })
		axios.get.mockResolvedValue({ data: { error: 'Server error: 503' } })

		await wrapper.vm.getBoards()
		await flushPromises()

		// emptying the list in silence loses the user boards that still exist
		expect(wrapper.vm.state.board_list.map((b) => b.name)).toEqual(['Sprint planning', 'Retro'])
		expect(showError).toHaveBeenCalledWith('Failed to get boards: NUITEQ did not answer with a list of boards')
	})

	it('shows an empty list rather than nothing at all when the page state is not a list', async () => {
		// the page controller guards this, so it is defence rather than a path
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
