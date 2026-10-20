/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import axios from '@nextcloud/axios'
import { showError, showSuccess } from '@nextcloud/dialogs'
import { confirmPassword } from '@nextcloud/password-confirmation'
import { flushPromises, mount } from '@vue/test-utils'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import AdminSettings from '../../components/AdminSettings.vue'
import { httpError, setInitialState } from '../helpers.js'

vi.mock('@nextcloud/axios', () => ({ default: { put: vi.fn() } }))
vi.mock('@nextcloud/dialogs', () => ({ showError: vi.fn(), showSuccess: vi.fn() }))
vi.mock('@nextcloud/password-confirmation', () => ({ confirmPassword: vi.fn() }))

const ADMIN_URL = '/index.php/apps/integration_nuiteq/admin-config'

/**
 * Mount the admin settings with an admin config of the test's choosing.
 *
 * @param config what the server put on the page
 */
function mountSettings(config = {}) {
	setInitialState('admin-config', { base_url: 'https://stage.example.com', client_key: '', ...config })
	return mount(AdminSettings)
}

/**
 * One of the text fields, by the placeholder the admin sees in it.
 *
 * @param wrapper the mounted settings
 * @param placeholder the placeholder text
 */
function field(wrapper, placeholder) {
	return wrapper.find(`input[placeholder="${placeholder}"]`)
}

describe('AdminSettings', () => {
	beforeEach(() => {
		vi.clearAllMocks()
		axios.put.mockResolvedValue({ data: {} })
		confirmPassword.mockResolvedValue()
	})

	afterEach(() => vi.useRealTimers())

	it('asks for what it shows under this app', () => {
		const wrapper = mountSettings()

		expect(wrapper.text()).not.toContain('[untranslatable')
		expect(wrapper.text()).toContain('Leave this empty to use the default client key.')
	})

	describe('typing in a field', () => {
		it('waits for the admin to stop typing before saving', async () => {
			vi.useFakeTimers()
			const wrapper = mountSettings()

			await field(wrapper, 'https://nuiteqstage.se').setValue('https://new.example.com')
			await vi.advanceTimersByTimeAsync(1999)
			expect(axios.put).not.toHaveBeenCalled()

			await vi.advanceTimersByTimeAsync(1)
			expect(axios.put).toHaveBeenCalledTimes(1)
		})

		it('saves the url the admin typed', async () => {
			vi.useFakeTimers()
			const wrapper = mountSettings()

			await field(wrapper, 'https://nuiteqstage.se').setValue('https://new.example.com')
			await vi.advanceTimersByTimeAsync(2000)

			expect(axios.put).toHaveBeenCalledWith(ADMIN_URL, {
				values: { base_url: 'https://new.example.com', client_key: '' },
			})
		})

		it('leaves the stored key alone while the field still shows the placeholder', async () => {
			vi.useFakeTimers()
			const wrapper = mountSettings({ client_key: 'dummySecret' })

			await field(wrapper, 'https://nuiteqstage.se').setValue('https://new.example.com')
			await vi.advanceTimersByTimeAsync(2000)

			expect(axios.put.mock.calls[0][1].values).not.toHaveProperty('client_key')
		})

		it('sends a key the admin actually typed', async () => {
			vi.useFakeTimers()
			const wrapper = mountSettings({ client_key: 'dummySecret' })

			await field(wrapper, 'Client key').setValue('a-real-key')
			await vi.advanceTimersByTimeAsync(2000)

			expect(axios.put.mock.calls[0][1].values.client_key).toBe('a-real-key')
		})
	})

	it('saves what the admin typed once the password is confirmed', async () => {
		const wrapper = mountSettings()

		await wrapper.vm.saveOptions({ base_url: 'https://new.example.com' })
		await flushPromises()

		expect(confirmPassword).toHaveBeenCalled()
		expect(axios.put).toHaveBeenCalledWith(ADMIN_URL, {
			values: { base_url: 'https://new.example.com' },
		})
		expect(showSuccess).toHaveBeenCalledWith('Nuiteq admin options saved')
	})

	it('says so and saves nothing when the admin dismisses the password dialog', async () => {
		confirmPassword.mockRejectedValue(new Error('Dialog closed'))
		const wrapper = mountSettings()

		await wrapper.vm.saveOptions({ base_url: 'https://new.example.com' })
		await flushPromises()

		expect(axios.put).not.toHaveBeenCalled()
		expect(showSuccess).not.toHaveBeenCalled()
		expect(showError)
			.toHaveBeenCalledWith('Password confirmation is required to save these options')
	})

	it('reports what the server said when saving fails', async () => {
		axios.put.mockRejectedValue({ response: { status: 400, data: { error: 'read-only config' } } })
		const wrapper = mountSettings()

		await wrapper.vm.saveOptions({ base_url: 'https://new.example.com' })
		await flushPromises()

		expect(showError).toHaveBeenCalledWith('Failed to save Nuiteq admin options: read-only config')
	})

	it('describes a failure by its status rather than printing the page it answered with', async () => {
		axios.put.mockRejectedValue(httpError(500, '<!DOCTYPE html><p>Exception: in /var/www/x.php</p>'))
		const wrapper = mountSettings()

		await wrapper.vm.saveOptions({ base_url: 'https://new.example.com' })
		await flushPromises()

		// the body used to be printed whole, and an answer without one left
		// the message ending in a colon
		expect(showError).toHaveBeenCalledWith('Failed to save Nuiteq admin options: The server answered 500')
	})
})
