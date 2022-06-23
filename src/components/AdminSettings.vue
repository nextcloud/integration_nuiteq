<template>
	<div id="nuiteq_prefs" class="section">
		<h2>
			<NuiteqIcon />
			<label>
				{{ t('integration_nuiteq', 'Nuiteq integration') }}
			</label>
		</h2>
		<div class="fields">
			<div class="field">
				<ServerIcon :size="20" />
				<label for="base-url">
					{{ t('integration_nuiteq', 'Default NUITEQ Stage URL for users') }}
				</label>
				<input id="base-url"
					v-model="state.base_url"
					type="text"
					placeholder="https://nuiteqstage.se"
					@input="onInput">
			</div>
			<div class="field">
				<KeyIcon :size="20" />
				<label for="base-url">
					{{ t('integration_nuiteq', 'Default NUITEQ Stage client ID for users') }}
				</label>
				<input id="base-url"
					v-model="state.client_id"
					type="text"
					:placeholder="t('integration_nuiteq', 'client ID')"
					@input="onInput">
				<p class="settings-hint">
					<InformationOutlineIcon :size="20" />
					{{ t('integration_nuiteq', 'Leave this empty to use the default client ID.') }}
				</p>
			</div>
		</div>
	</div>
</template>

<script>
import { loadState } from '@nextcloud/initial-state'
import { generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import { delay } from '../utils'
import { showSuccess, showError } from '@nextcloud/dialogs'

import ServerIcon from 'vue-material-design-icons/Server'
import InformationOutlineIcon from 'vue-material-design-icons/InformationOutline'
import KeyIcon from 'vue-material-design-icons/Key'
import NuiteqIcon from './NuiteqIcon'

export default {
	name: 'AdminSettings',

	components: {
		NuiteqIcon,
		ServerIcon,
		InformationOutlineIcon,
		KeyIcon,
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
				that.saveOptions()
			}, 2000)()
		},
		saveOptions() {
			const req = {
				values: {
					base_url: this.state.base_url,
					client_id: this.state.client_id,
				},
			}
			const url = generateUrl('/apps/integration_nuiteq/admin-config')
			axios.put(url, req).then((response) => {
				showSuccess(t('integration_nuiteq', 'Nuiteq admin options saved'))
			}).catch((error) => {
				showError(
					t('integration_nuiteq', 'Failed to save Nuiteq admin options')
						+ ': ' + (error.response?.request?.responseText ?? '')
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
				width: 300px;
			}

			input {
				width: 200px;
			}
		}
	}
	.settings-hint {
		display: flex;
		> * {
			margin: 0 4px;
		}
	}
}

</style>
