/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import axios from '@nextcloud/axios'
import { showError, showSuccess } from '@nextcloud/dialogs'
import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import PersonalSettings from '../../components/PersonalSettings.vue'
import { httpError, setInitialState } from '../helpers.js'

vi.mock('@nextcloud/axios', () => ({ default: { get: vi.fn(), put: vi.fn() } }))
vi.mock('@nextcloud/dialogs', () => ({ showError: vi.fn(), showSuccess: vi.fn() }))

const CONFIG_URL = '/index.php/apps/integration_nuiteq/config'

/**
 * Mount the personal settings with the state the server put on the page.
 *
 * @param state what to add or override
 */
function mountSettings(state = {}) {
	setInitialState('nuiteq-state', {
		base_url: 'https://stage.example.com',
		user_name: '',
		api_key: false,
		...state,
	})
	return mount(PersonalSettings)
}

/**
 * Fill in the credentials and press Connect, the way a user would: the button
 * stays disabled until both fields carry something.
 *
 * @param wrapper the mounted settings
 */
async function connect(wrapper) {
	await wrapper.find('input[placeholder="NUITEQ login"]').setValue('jane')
	await wrapper.find('input[placeholder="NUITEQ password"]').setValue('hunter2')

	const button = wrapper.find('#nuiteq-connect')
	expect(button.attributes('disabled')).toBeUndefined()
	await button.trigger('click')
}

describe('PersonalSettings', () => {
	beforeEach(() => {
		vi.clearAllMocks()
		axios.put.mockResolvedValue({ data: {} })
	})

	it('keeps the account the server answered with', async () => {
		axios.put.mockResolvedValue({ data: { user_name: 'jane' } })
		const wrapper = mountSettings()

		await connect(wrapper)
		await flushPromises()

		expect(axios.put).toHaveBeenCalledWith(CONFIG_URL, {
			values: {
				login: 'jane',
				password: 'hunter2',
				base_url: 'https://stage.example.com',
				client_key: undefined,
			},
		})
		expect(showSuccess).toHaveBeenCalledWith('Successfully connected to NUITEQ!')
		expect(wrapper.vm.state.user_name).toBe('jane')
	})

	it('reports what the server said about a refused connection', async () => {
		axios.put.mockRejectedValue({ response: { data: { error: 'wrong password' } } })
		const wrapper = mountSettings()

		await connect(wrapper)
		await flushPromises()

		expect(showError).toHaveBeenCalledWith('wrong password')
		expect(wrapper.vm.loading).toBe(false)
	})

	it('stops waiting when the server did not answer at all', async () => {
		// a network failure, a timeout or a CORS rejection gives axios an error
		// with no response: the catch used to throw and leave the button spinning
		axios.put.mockRejectedValue(new Error('Network Error'))
		const wrapper = mountSettings()

		await connect(wrapper)
		await flushPromises()

		expect(showError).toHaveBeenCalled()
		expect(wrapper.vm.loading).toBe(false)
	})

	it('reports what the server wrote when it said nothing useful', async () => {
		axios.put.mockRejectedValue(httpError(500, 'gateway timeout'))
		const wrapper = mountSettings()

		await connect(wrapper)
		await flushPromises()

		expect(showError).toHaveBeenCalledWith('Failed to save NUITEQ options: gateway timeout')
		expect(wrapper.vm.loading).toBe(false)
	})
})
