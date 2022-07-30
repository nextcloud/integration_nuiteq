import Vue from 'vue'
import './bootstrap'
import { loadState } from '@nextcloud/initial-state'
import NuiteqModalWrapper from './components/NuiteqModalWrapper'

function init() {
	if (!OCA.Nuiteq) {
		/**
		 * @namespace
		 */
		OCA.Nuiteq = {}
	}

	const wrapperId = 'nuiteqModalWrapper'
	const wrapperElement = document.createElement('div')
	wrapperElement.id = wrapperId
	document.body.append(wrapperElement)

	const View = Vue.extend(NuiteqModalWrapper)
	OCA.Nuiteq.NuiteqModalWrapperVue = new View().$mount('#' + wrapperId)

	OCA.Nuiteq.openModal = (roomUrl) => {
		OCA.Nuiteq.NuiteqModalWrapperVue.openOn(roomUrl)
	}
}

function listen(baseUrl) {
	const body = document.querySelector('body')
	body.addEventListener('click', (e) => {
		const link = (e.target.tagName === 'A')
			? e.target
			: (e.target.parentElement?.tagName === 'A')
				? e.target.parentElement
				: null
		if (link !== null) {
			const href = link.getAttribute('href')
			if (href.startsWith(baseUrl + '/')) {
				e.preventDefault()
				e.stopPropagation()
				OCA.Nuiteq.openModal(href)
			}
		}
	})
}

const baseUrl = loadState('integration_nuiteq', 'base_url')
const overrideLinkClick = loadState('integration_nuiteq', 'override_link_click')
if (baseUrl) {
	init()
	console.debug('!!! Nuiteq standalone modal is ready', baseUrl)
	if (overrideLinkClick) {
		console.debug('Nuiteq will handle clicks on links')
		listen(baseUrl)
	}
} else {
	console.debug('!!! Nuiteq standalone: disabled')
}
