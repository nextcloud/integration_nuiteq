<!--
  - SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div id="nuiteq_prefs" class="section">
		<h2>
			<NuiteqIcon />
			<label>
				{{ t('integration_nuiteq', 'Nuiteq integration') }}
			</label>
		</h2>
		<div class="nuiteq-content">
			<NcTextField
				v-model="state.base_url"
				:label="t('integration_nuiteq', 'Default NUITEQ Stage URL for users')"
				placeholder="https://nuiteqstage.se"
				:show-trailing-button="!!state.base_url"
				@trailing-button-click="state.base_url = ''; onInput()"
				@update:model-value="onInput">
				<template #icon>
					<ServerOutlineIcon :size="20" />
				</template>
			</NcTextField>
			<NcTextField
				v-model="state.client_key"
				type="password"
				:label="t('integration_nuiteq', 'Default NUITEQ Stage client key for users')"
				:placeholder="t('integration_nuiteq', 'Client key')"
				:show-trailing-button="!!state.client_key"
				@trailing-button-click="state.client_key = ''; onInput()"
				@update:model-value="onInput">
				<template #icon>
					<KeyOutlineIcon :size="20" />
				</template>
			</NcTextField>
			<NcNoteCard type="info">
				{{ t('integration_nuiteq', 'Leave this empty to use the default client key.') }}
			</NcNoteCard>
		</div>
	</div>
</template>

<script>
import ServerOutlineIcon from 'vue-material-design-icons/ServerOutline.vue'
import KeyOutlineIcon from 'vue-material-design-icons/KeyOutline.vue'
import NuiteqIcon from './icons/NuiteqIcon.vue'

import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcTextField from '@nextcloud/vue/components/NcTextField'

import { loadState } from '@nextcloud/initial-state'
import { generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import { delay } from '../utils.js'
import { showSuccess, showError } from '@nextcloud/dialogs'
import { confirmPassword } from '@nextcloud/password-confirmation'

export default {
	name: 'AdminSettings',

	components: {
		NuiteqIcon,
		ServerOutlineIcon,
		KeyOutlineIcon,
		NcNoteCard,
		NcTextField,
	},

	props: [],

	data() {
		return {
			state: loadState('integration_nuiteq', 'admin-config'),
		}
	},

	watch: {
	},

	mounted() {
	},

	methods: {
		onInput() {
			const that = this
			delay(() => {
				const values = {
					base_url: this.state.base_url,
				}
				if (this.state.client_key !== 'dummySecret') {
					values.client_key = this.state.client_key
				}
				that.saveOptions(values)
			}, 2000)()
		},
		async saveOptions(values) {
			await confirmPassword()
			const req = {
				values,
			}
			const url = generateUrl('/apps/integration_nuiteq/admin-config')
			axios.put(url, req).then((response) => {
				showSuccess(t('integration_nuiteq', 'Nuiteq admin options saved'))
			}).catch((error) => {
				showError(
					t('integration_nuiteq', 'Failed to save Nuiteq admin options')
						+ ': ' + (error.response?.request?.responseText ?? ''),
				)
				console.debug(error)
			})
		},
	},
}
</script>

<style scoped lang="scss">
#nuiteq_prefs {
	h2 {
		display: flex;
		align-items: center;
		gap: 8px;
		justify-content: start;
	}

	.nuiteq-content {
		margin-left: 40px;
		display: flex;
		flex-direction: column;
		gap: 4px;
		max-width: 800px;
	}
}

</style>
