/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import axios from '@nextcloud/axios'
import { showError, showSuccess } from '@nextcloud/dialogs'
import { confirmPassword } from '@nextcloud/password-confirmation'
import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
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

describe('AdminSettings', () => {
	beforeEach(() => {
		vi.clearAllMocks()
		axios.put.mockResolvedValue({ data: {} })
		confirmPassword.mockResolvedValue()
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
		axios.put.mockRejectedValue(httpError(500, 'read-only config'))
		const wrapper = mountSettings()

		await wrapper.vm.saveOptions({ base_url: 'https://new.example.com' })
		await flushPromises()

		expect(showError).toHaveBeenCalledWith('Failed to save Nuiteq admin options: read-only config')
	})
})
