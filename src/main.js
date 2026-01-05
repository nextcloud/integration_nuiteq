/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { createApp } from 'vue'
import App from './App.vue'

import '../css/main.scss'

document.addEventListener('DOMContentLoaded', (event) => {
	const app = createApp(App)
	app.mixin({ methods: { t, n } })
	app.mount('#content')
})
