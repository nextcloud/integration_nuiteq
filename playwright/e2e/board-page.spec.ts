/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

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
})
