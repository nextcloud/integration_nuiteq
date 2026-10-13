/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import axios from '@nextcloud/axios'
import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import SendModal from '../../../components/talk/SendModal.vue'

vi.mock('@nextcloud/axios', () => ({ default: { get: vi.fn(), post: vi.fn() } }))
vi.mock('@nextcloud/dialogs', () => ({ showError: vi.fn(), showSuccess: vi.fn() }))

const BOARD = { id: 'b-1', name: 'Sprint planning' }

/**
 * A conversation as Talk's own API returns it.
 *
 * @param over what to add or override
 */
function conversation(over = {}) {
	return {
		id: 42,
		token: 'tok42',
		name: 'design-team',
		displayName: 'Design team',
		description: 'Where the mockups land',
		readOnly: 0,
		type: 2,
		...over,
	}
}

/**
 * Mount the modal with the conversations Talk answered with. NcModal teleports
 * its content to the body, so the assertions read the document, not the wrapper.
 *
 * @param rooms what /apps/spreed/api/v4/room returns
 */
async function mountModal(rooms = []) {
	axios.get.mockResolvedValue({ data: { ocs: { data: rooms } } })
	const wrapper = mount(SendModal, { props: { board: BOARD, nuiteqUrl: 'https://nuiteq.example.com' } })
	await flushPromises()
	return wrapper
}

/**
 * The text of part of the modal as it reaches the screen.
 *
 * @param selector the part of the modal to read
 */
function shown(selector) {
	return document.body.querySelector(selector)?.textContent.replace(/\s+/g, ' ').trim() ?? ''
}

describe('SendModal', () => {
	beforeEach(() => vi.clearAllMocks())

	it('names every conversation it offers', async () => {
		await mountModal([
			conversation(),
			conversation({ id: 43, name: 'release', displayName: 'Release crew' }),
		])

		// the name used to reach the row as a title attribute, which is a
		// tooltip: the row itself was blank
		expect(document.body.querySelectorAll('#conversations li')).toHaveLength(2)
		expect(shown('#conversations')).toContain('Design team')
		expect(shown('#conversations')).toContain('Release crew')
	})

	it('describes every conversation it offers', async () => {
		await mountModal([conversation()])

		expect(shown('#conversations')).toContain('Where the mockups land')
	})

	it('offers no conversation Talk marked read only', async () => {
		await mountModal([conversation({ readOnly: 1 })])

		expect(document.body.querySelectorAll('#conversations li')).toHaveLength(0)
	})
})
