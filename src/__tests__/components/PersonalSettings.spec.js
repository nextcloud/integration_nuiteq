/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import axios from '@nextcloud/axios'
import { showError, showSuccess } from '@nextcloud/dialogs'
import { flushPromises, mount } from '@vue/test-utils'
import { AxiosError } from 'axios'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import PersonalSettings from '../../components/PersonalSettings.vue'
import { refusal, setInitialState } from '../helpers.js'

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

/**
 * Change the server address and let the two second wait run out, the way the
 * settings save on their own.
 *
 * @param wrapper the mounted settings
 */
async function changeSetting(wrapper) {
	vi.useFakeTimers()
	await wrapper.find('input[placeholder="https://nuiteqstage.se"]').setValue('https://new.example.com')
	await vi.advanceTimersByTimeAsync(2000)
	vi.useRealTimers()
}

/**
 * Press Disconnect.
 *
 * @param wrapper the mounted settings, connected
 */
async function disconnect(wrapper) {
	await wrapper.find('#nuiteq-rm-cred').trigger('click')
}

describe('PersonalSettings', () => {
	beforeEach(() => {
		vi.clearAllMocks()
		axios.put.mockResolvedValue({ data: {} })
	})

	afterEach(() => vi.useRealTimers())

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

	it('connects to the Stage URL the user typed', async () => {
		// the field also saves on its own two seconds later
		vi.useFakeTimers()
		axios.put.mockResolvedValue({ data: { user_name: 'jane' } })
		const wrapper = mountSettings()

		await wrapper.find('input[placeholder="https://nuiteqstage.se"]').setValue('https://own.example.com')
		await connect(wrapper)
		await flushPromises()

		expect(axios.put).toHaveBeenCalledTimes(1)
		expect(axios.put).toHaveBeenCalledWith(CONFIG_URL, {
			values: { login: 'jane', password: 'hunter2', base_url: 'https://own.example.com' },
		})
	})

	it('reports what the server said about a refused connection', async () => {
		axios.put.mockRejectedValue(refusal(400, { error: 'wrong password' }))
		const wrapper = mountSettings()

		await connect(wrapper)
		await flushPromises()

		expect(showError).toHaveBeenCalledTimes(1)
		expect(showError).toHaveBeenCalledWith('wrong password')
		expect(wrapper.vm.loading).toBe(false)
	})

	it('stops waiting when the server did not answer at all', async () => {
		// a network failure or a CORS rejection gives axios an error with no
		// response, whose message is axios's own English
		axios.put.mockRejectedValue(new AxiosError('Network Error', AxiosError.ERR_NETWORK, {}, {}))
		const wrapper = mountSettings()

		await connect(wrapper)
		await flushPromises()

		expect(showError).toHaveBeenCalledTimes(1)
		expect(showError).toHaveBeenCalledWith('Failed to save NUITEQ options: The server could not be reached')
		expect(wrapper.vm.loading).toBe(false)
	})

	it('does not read a server error that is not a sentence out to the user', async () => {
		axios.put.mockRejectedValue(refusal(503, { error: { message: 'nope' } }))
		const wrapper = mountSettings()

		await connect(wrapper)
		await flushPromises()

		// the server's own sentence is shown as the whole message, so anything
		// else reaching showError would be read as one
		expect(showError).toHaveBeenCalledTimes(1)
		expect(showError).toHaveBeenCalledWith('Failed to save NUITEQ options: The server answered 503')
	})

	it.each([
		['the login field', 'NUITEQ login'],
		['the password field', 'NUITEQ password'],
	])('connects when the user presses Enter in %s', async (_, placeholder) => {
		axios.put.mockResolvedValue({ data: { user_name: 'jane' } })
		const wrapper = mountSettings()

		await wrapper.find('input[placeholder="NUITEQ login"]').setValue('jane')
		await wrapper.find('input[placeholder="NUITEQ password"]').setValue('hunter2')
		await wrapper.find(`input[placeholder="${placeholder}"]`).trigger('keyup.enter')
		await flushPromises()

		// Enter in either credential submits the form, and only the button
		// was pinned
		expect(axios.put).toHaveBeenCalledWith(CONFIG_URL, {
			values: { login: 'jane', password: 'hunter2', base_url: 'https://stage.example.com' },
		})
	})

	it('says something when the rejection carries nothing at all', async () => {
		axios.put.mockRejectedValue(undefined)
		const wrapper = mountSettings()

		await connect(wrapper)
		await flushPromises()

		// reading .response off it threw out of the handler before anything
		// was shown, so the user was told nothing at all
		expect(showError).toHaveBeenCalledTimes(1)
		expect(showError).toHaveBeenCalledWith('Failed to save NUITEQ options: The server could not be reached')
		expect(wrapper.vm.loading).toBe(false)
	})

	it('does not show a reason that is only spaces as the message', async () => {
		axios.put.mockRejectedValue(refusal(500, { error: '   ' }))
		const wrapper = mountSettings()

		await connect(wrapper)
		await flushPromises()

		// shown bare, it was a toast with nothing in it
		expect(showError).toHaveBeenCalledTimes(1)
		expect(showError).toHaveBeenCalledWith('Failed to save NUITEQ options: The server answered 500')
	})

	it('tells the server to forget the key when the user disconnects', async () => {
		const wrapper = mountSettings({ user_name: 'jane', api_key: true })

		await wrapper.find('#nuiteq-rm-cred').trigger('click')
		await flushPromises()

		// the server keeps the key until it is told to forget it
		expect(axios.put).toHaveBeenCalledWith(CONFIG_URL, { values: { api_key: '' } })
		expect(wrapper.text()).toContain('Connect to NUITEQ Stage')
	})

	it.each([
		['a connection', {}, connect],
		['a changed setting', {}, changeSetting],
		['a disconnect', { user_name: 'jane', api_key: true }, disconnect],
	])('says nothing was saved when Nextcloud itself refuses %s', async (_, state, act) => {
		axios.put.mockRejectedValue(refusal(401, { message: 'Current user is not logged in' }))
		const wrapper = mountSettings(state)

		await act(wrapper)
		await flushPromises()

		// after a change or a disconnect the form already shows what the user
		// asked for, so the prefix is all that says it was not saved
		expect(showError).toHaveBeenCalledTimes(1)
		expect(showError).toHaveBeenCalledWith('Failed to save NUITEQ options: Current user is not logged in')
	})

	it('offers no connection until both credentials are typed', async () => {
		const wrapper = mountSettings()
		const button = () => wrapper.find('#nuiteq-connect')

		expect(button().attributes('disabled')).toBeDefined()

		await wrapper.find('input[placeholder="NUITEQ login"]').setValue('jane')
		expect(button().attributes('disabled')).toBeDefined()

		await wrapper.find('input[placeholder="NUITEQ password"]').setValue('hunter2')
		expect(button().attributes('disabled')).toBeUndefined()

		await wrapper.find('input[placeholder="NUITEQ login"]').setValue('')
		expect(button().attributes('disabled')).toBeDefined()
	})

	it('waits for the user to stop typing before saving', async () => {
		vi.useFakeTimers()
		const wrapper = mountSettings()

		await wrapper.find('input[placeholder="https://nuiteqstage.se"]').setValue('https://new.example.com')
		await vi.advanceTimersByTimeAsync(1999)
		expect(axios.put).not.toHaveBeenCalled()

		await vi.advanceTimersByTimeAsync(1)
		expect(axios.put).toHaveBeenCalledTimes(1)
		expect(axios.put).toHaveBeenCalledWith(CONFIG_URL, {
			values: { base_url: 'https://new.example.com', client_key: undefined },
		})
	})

	it('saves the client key the user typed', async () => {
		vi.useFakeTimers()
		const wrapper = mountSettings()

		await wrapper.find('input[placeholder="Client key"]').setValue('my-own-key')
		await vi.advanceTimersByTimeAsync(1999)
		expect(axios.put).not.toHaveBeenCalled()

		await vi.advanceTimersByTimeAsync(1)
		expect(axios.put).toHaveBeenCalledWith(CONFIG_URL, {
			values: { base_url: 'https://stage.example.com', client_key: 'my-own-key' },
		})
	})

	it.each([
		['the Stage URL', 'https://nuiteqstage.se', { base_url: '', client_key: 'a-key' }],
		['the client key', 'Client key', { base_url: 'https://stage.example.com', client_key: '' }],
	])('saves %s the user cleared', async (_, placeholder, values) => {
		vi.useFakeTimers()
		const wrapper = mountSettings({ client_key: 'a-key' })
		const field = wrapper.findAll('.input-field')
			.find((candidate) => candidate.find(`input[placeholder="${placeholder}"]`).exists())

		await field.find('.input-field__trailing-button').trigger('click')
		await vi.advanceTimersByTimeAsync(2000)

		expect(field.find('input').element.value).toBe('')
		expect(axios.put).toHaveBeenCalledWith(CONFIG_URL, { values })
	})

	it('asks for what it shows under this app', () => {
		const connected = mountSettings({ user_name: 'jane', api_key: true })

		expect(connected.text()).not.toContain('[untranslatable')
		expect(connected.text()).toContain('Connected as jane')
		expect(connected.text()).toContain('Disconnect from NUITEQ')

		const disconnected = mountSettings()

		expect(disconnected.text()).not.toContain('[untranslatable')
		expect(disconnected.text()).toContain('Connect to NUITEQ Stage')
		expect(disconnected.text()).toContain('Leave the client key empty to use the default one.')
	})

	it('describes a failure the server did not explain by its status', async () => {
		axios.put.mockRejectedValue(refusal(500, 'gateway timeout'))
		const wrapper = mountSettings()

		await connect(wrapper)
		await flushPromises()

		expect(showError).toHaveBeenCalledTimes(1)
		expect(showError).toHaveBeenCalledWith('Failed to save NUITEQ options: The server answered 500')
		expect(wrapper.vm.loading).toBe(false)
	})
})
