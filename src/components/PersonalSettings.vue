<template>
	<div id="nuiteq_prefs" class="section">
		<h2 v-if="showTitle">
			<NuiteqIcon />
			<label>
				{{ t('integration_nuiteq', 'Nuiteq integration') }}
			</label>
		</h2>
		<div class="fields">
			<div class="field">
				<ServerIcon :size="20" />
				<label for="base-url">
					{{ t('integration_nuiteq', 'NUITEQ Stage URL') }}
				</label>
				<input id="base-url"
					v-model="state.base_url"
					type="text"
					placeholder="https://nuiteqstage.se"
					:disabled="connected === true"
					@input="onInput">
			</div>
			<div v-show="!connected" class="field">
				<KeyIcon :size="20" />
				<label for="base-url">
					{{ t('integration_nuiteq', 'Client key') }}
				</label>
				<input id="base-url"
					v-model="state.client_key"
					type="text"
					:placeholder="t('integration_nuiteq', 'client key')"
					@input="onInput">
				<p class="settings-hint">
					<InformationOutlineIcon :size="20" />
					{{ t('integration_nuiteq', 'Leave this empty to use the default client key.') }}
				</p>
			</div>
			<div v-show="!connected" class="field">
				<AccountIcon :size="20" />
				<label
					for="nuiteq-login">
					{{ t('integration_nuiteq', 'Login') }}
				</label>
				<input
					id="nuiteq-login"
					v-model="login"
					type="text"
					:placeholder="t('integration_nuiteq', 'NUITEQ login')"
					@keyup.enter="onConnectClick">
			</div>
			<div v-show="!connected" class="field">
				<LockIcon :size="20" />
				<label
					for="nuiteq-password">
					{{ t('integration_nuiteq', 'Password') }}
				</label>
				<input
					id="nuiteq-password"
					v-model="password"
					type="password"
					:placeholder="t('integration_nuiteq', 'NUITEQ password')"
					@keyup.enter="onConnectClick">
			</div>
		</div>
		<Button v-if="!connected && login && password"
			id="nuiteq-connect"
			:disabled="loading === true"
			:class="{ loading }"
			@click="onConnectClick">
			<template #icon>
				<OpenInNewIcon />
			</template>
			{{ t('integration_nuiteq', 'Connect to NUITEQ Stage') }}
		</Button>
		<div v-if="connected" class="nuiteq-connected-wrapper">
			<label class="nuiteq-connected">
				<CheckIcon />
				<label>
					{{ t('integration_nuiteq', 'Connected as {user}', { user: state.user_name }) }}
				</label>
			</label>
			<Button id="nuiteq-rm-cred" @click="onLogoutClick">
				<template #icon>
					<CloseIcon />
				</template>
				{{ t('integration_nuiteq', 'Disconnect from NUITEQ') }}
			</Button>
			<span />
		</div>
	</div>
</template>

<script>
import { loadState } from '@nextcloud/initial-state'
import { generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import { delay } from '../utils'
import { showSuccess, showError } from '@nextcloud/dialogs'

import Button from '@nextcloud/vue/dist/Components/Button'
import NuiteqIcon from './NuiteqIcon'
import ServerIcon from 'vue-material-design-icons/Server'
import CheckIcon from 'vue-material-design-icons/Check'
import CloseIcon from 'vue-material-design-icons/Close'
import AccountIcon from 'vue-material-design-icons/Account'
import LockIcon from 'vue-material-design-icons/Lock'
import OpenInNewIcon from 'vue-material-design-icons/OpenInNew'
import InformationOutlineIcon from 'vue-material-design-icons/InformationOutline'
import KeyIcon from 'vue-material-design-icons/Key'

export default {
	name: 'PersonalSettings',

	components: {
		Button,
		NuiteqIcon,
		ServerIcon,
		CheckIcon,
		OpenInNewIcon,
		AccountIcon,
		LockIcon,
		CloseIcon,
		KeyIcon,
		InformationOutlineIcon,
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
			return this.state.user_name && this.state.user_name !== ''
		},
	},

	watch: {
	},

	mounted() {
	},

	methods: {
		onInput() {
			const that = this
			delay(() => {
				that.saveOptions({
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
						+ ': ' + (error.response?.request?.responseText ?? '')
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
		label {
			margin-left: 8px;
		}
	}

	.fields {
		display: flex;
		flex-direction: column;

		.field {
			display: flex;
			align-items: center;
			margin: 5px 0 5px 0;

			> * {
				margin: 0 5px 0 5px;
			}

			label {
				width: 150px;
			}

			input {
				width: 200px;
			}
		}
	}

	.nuiteq-connected-wrapper {
		display: flex;
		align-items: center;
		> label {
			display: flex;
			align-items: center;
			margin-right: 12px;
			> label {
				margin-left: 8px;
			}
		}
	}

	.settings-hint {
		display: flex;
		opacity: 0.7;
		> * {
			margin: 0 4px;
		}
	}
}

</style>
