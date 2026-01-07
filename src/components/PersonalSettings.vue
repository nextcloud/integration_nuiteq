<!--
  - SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div id="nuiteq_prefs" class="section">
		<h2 v-if="showTitle">
			<NuiteqIcon />
			<label>
				{{ t('integration_nuiteq', 'Nuiteq integration') }}
			</label>
		</h2>
		<div class="nuiteq-content">
			<NcTextField
				v-model="state.base_url"
				:label="t('integration_nuiteq', 'NUITEQ Stage URL')"
				placeholder="https://nuiteqstage.se"
				:disabled="connected === true"
				:show-trailing-button="!!state.base_url"
				@trailing-button-click="state.base_url = ''; onInput()"
				@update:model-value="onInput">
				<template #icon>
					<ServerOutlineIcon :size="20" />
				</template>
			</NcTextField>
			<NcNoteCard v-if="!connected" type="info">
				{{ t('integration_nuiteq', 'Leave the client key empty to use the default one.') }}
			</NcNoteCard>
			<NcTextField v-if="!connected"
				v-model="state.client_key"
				type="password"
				:label="t('integration_nuiteq', 'Client key')"
				:placeholder="t('integration_nuiteq', 'Client key')"
				:show-trailing-button="!!state.client_key"
				@trailing-button-click="state.client_key = ''; onInput()"
				@update:model-value="onInput">
				<template #icon>
					<KeyOutlineIcon :size="20" />
				</template>
			</NcTextField>
			<NcTextField v-if="!connected"
				v-model="login"
				:label="t('integration_nuiteq', 'Login')"
				:placeholder="t('integration_nuiteq', 'NUITEQ login')"
				@keyup.enter="onConnectClick">
				<template #icon>
					<AccountIcon :size="20" />
				</template>
			</NcTextField>
			<NcTextField v-if="!connected"
				v-model="password"
				type="password"
				:label="t('integration_nuiteq', 'Password')"
				:placeholder="t('integration_nuiteq', 'NUITEQ password')"
				@keyup.enter="onConnectClick">
				<template #icon>
					<LockIcon :size="20" />
				</template>
			</NcTextField>
			<NcButton v-if="!connected"
				id="nuiteq-connect"
				:disabled="loading || !login || !password"
				:class="{ loading }"
				@click="onConnectClick">
				<template #icon>
					<OpenInNewIcon />
				</template>
				{{ t('integration_nuiteq', 'Connect to NUITEQ Stage') }}
			</NcButton>
			<div v-if="connected" class="nuiteq-connected-wrapper">
				<label class="nuiteq-connected">
					<CheckIcon />
					<label>
						{{ t('integration_nuiteq', 'Connected as {user}', { user: state.user_name }) }}
					</label>
				</label>
				<NcButton id="nuiteq-rm-cred" @click="onLogoutClick">
					<template #icon>
						<CloseIcon />
					</template>
					{{ t('integration_nuiteq', 'Disconnect from NUITEQ') }}
				</NcButton>
				<span />
			</div>
		</div>
	</div>
</template>

<script>
import ServerOutlineIcon from 'vue-material-design-icons/ServerOutline.vue'
import CheckIcon from 'vue-material-design-icons/Check.vue'
import CloseIcon from 'vue-material-design-icons/Close.vue'
import AccountIcon from 'vue-material-design-icons/Account.vue'
import LockIcon from 'vue-material-design-icons/Lock.vue'
import OpenInNewIcon from 'vue-material-design-icons/OpenInNew.vue'
import KeyOutlineIcon from 'vue-material-design-icons/KeyOutline.vue'

import NuiteqIcon from './icons/NuiteqIcon.vue'

import NcButton from '@nextcloud/vue/components/NcButton'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcTextField from '@nextcloud/vue/components/NcTextField'

import { loadState } from '@nextcloud/initial-state'
import { generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import { showSuccess, showError } from '@nextcloud/dialogs'

import { delay } from '../utils.js'

export default {
	name: 'PersonalSettings',

	components: {
		NcButton,
		NcNoteCard,
		NcTextField,
		NuiteqIcon,
		ServerOutlineIcon,
		CheckIcon,
		OpenInNewIcon,
		AccountIcon,
		LockIcon,
		CloseIcon,
		KeyOutlineIcon,
	},

	props: {
		showTitle: {
			type: Boolean,
			default: true,
		},
	},

	data() {
		return {
			state: loadState('integration_nuiteq', 'nuiteq-state'),
			login: '',
			password: '',
			loading: false,
		}
	},

	computed: {
		connected() {
			return !!this.state.user_name
		},
	},

	watch: {
	},

	mounted() {
	},

	methods: {
		onInput() {
			delay(() => {
				this.saveOptions({
					base_url: this.state.base_url,
					client_key: this.state.client_key,
				})
			}, 2000)()
		},
		saveOptions(values) {
			const req = {
				values,
			}
			const url = generateUrl('/apps/integration_nuiteq/config')
			axios.put(url, req).then((response) => {
				if (response.data.user_name) {
					showSuccess(t('integration_mattermost', 'Successfully connected to NUITEQ!'))
					this.state.user_name = response.data.user_name
					this.$emit('connected', this.state.user_name, this.state.base_url)
				} else {
					showSuccess(t('integration_nuiteq', 'Nuiteq options saved'))
				}
			}).catch((error) => {
				console.debug(error.response)
				if (error.response.data.error) {
					showError(error.response.data.error)
				} else {
					showError(
						t('integration_nuiteq', 'Failed to save NUITEQ options')
						+ ': ' + (error.response?.request?.responseText ?? ''),
					)
				}
				console.error(error)
			}).then(() => {
				this.loading = false
			})
		},
		onConnectClick() {
			this.loading = true
			this.saveOptions({
				login: this.login,
				password: this.password,
				base_url: this.state.base_url,
			})
		},
		onLogoutClick() {
			this.login = ''
			this.password = ''
			this.state.user_name = ''
			this.saveOptions({ api_key: '' })
		},
	},
}
</script>

<style scoped lang="scss">
#nuiteq_prefs {
	h2 {
		display: flex;
		align-items: center;
		justify-content: start;
		gap: 8px;
	}

	.nuiteq-content {
		margin-left: 40px;
		display: flex;
		flex-direction: column;
		gap: 4px;
		max-width: 800px;
	}

	#nuiteq-connect {
		margin-left: 30px;
	}

	.nuiteq-connected-wrapper {
		display: flex;
		align-items: center;
		margin-left: 30px;
		> label {
			display: flex;
			align-items: center;
			margin-right: 12px;
			> label {
				margin-left: 8px;
			}
		}
	}
}

</style>
