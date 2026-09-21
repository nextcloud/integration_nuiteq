/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { login } from '@nextcloud/e2e-test-server/playwright'
import { test as base, expect } from '@playwright/test'

// the test container always has this admin user
const admin = { userId: 'admin', password: 'admin' }

// Every test also fails on an uncaught exception, or on a failing request to one of the app's own routes.
// Errors of other apps on the instance are ignored on purpose.
const test = base.extend<{ appErrors: void }>({
	appErrors: [async ({ page }, use) => {
		const errors: string[] = []
		page.on('pageerror', (error) => errors.push(`uncaught: ${error.message}`))
		page.on('response', (response) => {
			if (response.status() >= 400 && response.url().includes('/integration_nuiteq/')) {
				errors.push(`${response.status()} ${response.request().method()} ${response.url()}`)
			}
		})
		await use()
		expect(errors).toEqual([])
	}, { auto: true }],
})

test.beforeEach(async ({ page }) => {
	await login(page.request, admin)
})

test.describe('Admin settings', () => {
	test.beforeEach(async ({ page }) => {
		await page.goto('settings/admin/connected-accounts')
	})

	test('show the NUITEQ section', async ({ page }) => {
		const section = page.locator('#nuiteq_prefs')
		await expect(section.getByRole('heading', { name: /Nuiteq integration/ })).toBeVisible()
		await expect(section.getByLabel('Default NUITEQ Stage URL for users', { exact: true })).toBeVisible()
		await expect(section.getByLabel('Default NUITEQ Stage client key for users', { exact: true })).toBeVisible()
	})

	test('save the default Stage URL', async ({ page }) => {
		const field = page.locator('#nuiteq_prefs').getByLabel('Default NUITEQ Stage URL for users', { exact: true })
		// the field saves a moment after the last keystroke
		const save = async (value: string) => {
			const saved = page.waitForResponse((response) => response.url().includes('/apps/integration_nuiteq/admin-config'))
			await field.fill(value)
			expect((await saved).ok()).toBe(true)
		}

		const before = await field.inputValue()
		const url = `https://stage-${Date.now()}.example.com`
		await save(url)
		try {
			await page.reload()
			await expect(field).toHaveValue(url)
		} finally {
			await save(before)
		}
	})
})

test.describe('Personal settings', () => {
	test('ask for the NUITEQ Stage credentials', async ({ page }) => {
		await page.goto('settings/user/connected-accounts')
		const section = page.locator('#nuiteq_prefs')
		await expect(section.getByRole('heading', { name: /Nuiteq integration/ })).toBeVisible()
		await expect(section.getByLabel('NUITEQ Stage URL', { exact: true })).toBeVisible()
		await expect(section.getByLabel('Login', { exact: true })).toBeVisible()
		await expect(section.getByLabel('Password', { exact: true })).toBeVisible()
	})
})
