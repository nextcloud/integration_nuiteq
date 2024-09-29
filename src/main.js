/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import Vue from 'vue'
import './bootstrap.js'
import App from './App.vue'

import '../css/main.scss'
import Tooltip from '@nextcloud/vue/dist/Directives/Tooltip.js'
import VueClipboard from 'vue-clipboard2'

Vue.directive('tooltip', Tooltip)
Vue.use(VueClipboard)

document.addEventListener('DOMContentLoaded', (event) => {
	const View = Vue.extend(App)
	new View().$mount('#content')
})
