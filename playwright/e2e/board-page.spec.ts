/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { Page } from '@playwright/test'

import { login } from '@nextcloud/e2e-test-server/playwright'
import { test as base, expect } from '@playwright/test'

// the test container always has this admin user
const admin = { userId: 'admin', password: 'admin' }

// Every test also fails on an uncaught exception, or on an unexpected failing request to one of the app's own routes.
// Errors of other apps on the instance are ignored on purpose.
const test = base.extend<{ appErrors: void }>({
	appErrors: [async ({ page }, use) => {
		const errors: string[] = []
		page.on('pageerror', (error) => errors.push(`uncaught: ${error.message}`))
		page.on('response', (response) => {
			if (response.status() >= 400 && response.url().includes('/integration_nuiteq/')) {
				errors.push(`${response.status()} ${response.request().method()} ${new URL(response.url()).pathname}`)
			}
		})
		await use()
		expect(errors).toEqual([])
	}, { auto: true }],
})

/**
 * Store user settings of the app, the way its settings section does.
 *
 * @param page a page of the logged in user, showing a page of the instance
 * @param values the settings to store
 */
async function setUserConfig(page: Page, values: Record<string, string>) {
	const requesttoken = await page.evaluate(() => (window as unknown as { OC: { requestToken: string } }).OC.requestToken)
	const response = await page.request.put('apps/integration_nuiteq/config', { headers: { requesttoken }, data: { values } })
	expect(response.ok()).toBe(true)
}

test.beforeEach(async ({ page }) => {
	await login(page.request, admin)
})

test.describe('Board page', () => {
	test('ask a user without a NUITEQ Stage account to connect one', async ({ page }) => {
		await page.goto('apps/integration_nuiteq/')

		await expect(page.getByText('You are not connected to NUITEQ Stage')).toBeVisible()
		await expect(page.getByLabel('NUITEQ Stage URL', { exact: true })).toBeVisible()
		await expect(page.getByLabel('Client key', { exact: true })).toBeVisible()
		await expect(page.getByLabel('Login', { exact: true })).toBeVisible()
		await expect(page.getByLabel('Password', { exact: true })).toBeVisible()
		await expect(page.getByRole('button', { name: 'Connect to NUITEQ Stage' })).toBeVisible()
	})

	// the board list of the initial state used to be whatever the API answered, and an answer
	// that is not a list of boards left the page empty
	test('show the page of an account whose key is not accepted any more', async ({ page }) => {
		await page.goto('apps/files/')
		await setUserConfig(page, { base_url: 'https://stage.invalid', user_name: 'someone', api_key: 'a-key-that-is-gone' })
		try {
			await page.goto('apps/integration_nuiteq/')

			await expect(page.getByText('You haven\'t created any boards yet')).toBeVisible()
			await expect(page.locator('#app-navigation-vue').getByRole('button', { name: 'Create a board' })).toBeVisible()
		} finally {
			await setUserConfig(page, { base_url: '', user_name: '', api_key: '' })
		}
	})
})
