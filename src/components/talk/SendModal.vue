<template>
	<Modal
		size="normal"
		@close="$emit('close')">
		<div class="modal-content">
			<Multiselect
				v-model="selectedRoom"
				class="multi-select"
				:placeholder="t('integration_nuiteq', 'Search rooms')"
				:options="rooms"
				:user-select="false"
				label="displayName"
				track-by="token"
				:loading="loadingOpenRooms"
				:internal-search="true"
				@search-change="findOpenRooms">
				<template #option="{option}">
					<Avatar v-if="option.type === 1"
						:size="34"
						:user="option.name" />
					<Avatar v-else
						:size="34"
						icon-class="icon-group" />
					<Highlight
						class="option-label"
						:text="option.displayName"
						:search="query" />
				</template>
				<template #singleLabel="{option}">
					<Avatar v-if="option.type === 1"
						:size="34"
						:user="option.name" />
					<Avatar v-else
						:size="34"
						icon-class="icon-group" />
					<label
						class="option-label">
						{{ option.displayName }}
					</label>
				</template>
				<template #noOptions>
					{{ t('integration_nuiteq', 'Start typing to search') }}
				</template>
			</Multiselect>
			<div class="spacer" />
			<div class="modal-footer">
				<Button @click="$emit('close')">
					<template #icon>
						<UndoIcon />
					</template>
					{{ t('integration_nuiteq', 'Cancel') }}
				</Button>
				<div class="spacer" />
				<Button type="primary"
					:disabled="selectedRoom === null"
					@click="onSendLinkClick">
					<template #icon>
						<SendIcon :class="{ 'icon-loading': loading }" />
					</template>
					{{ t('integration_nuiteq', 'Send') }}
				</Button>
			</div>
		</div>
	</Modal>
</template>

<script>

import SendIcon from 'vue-material-design-icons/Send'
import UndoIcon from 'vue-material-design-icons/Undo'
import Modal from '@nextcloud/vue/dist/Components/Modal'
import Avatar from '@nextcloud/vue/dist/Components/Avatar'
import Button from '@nextcloud/vue/dist/Components/Button'
import Multiselect from '@nextcloud/vue/dist/Components/Multiselect'
import Highlight from '@nextcloud/vue/dist/Components/Highlight'
import { showSuccess, showError } from '@nextcloud/dialogs'
import { generateOcsUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'

export default {
	name: 'SendModal',

	components: {
		Button,
		Avatar,
		Modal,
		Multiselect,
		Highlight,
		SendIcon,
		UndoIcon,
	},

	props: {
		board: {
			type: Object,
			required: true,
		},
		nuiteqUrl: {
			type: String,
			required: true,
		},
	},

	data() {
		return {
			opened: false,
			myRooms: [],
			openRooms: [],
			loadingOpenRooms: false,
			selectedRoom: null,
			loading: false,
			query: '',
		}
	},

	computed: {
		publicLink() {
			return this.nuiteqUrl + '/board/' + this.board.id
		},
		rooms() {
			return [
				...this.myRooms,
				...this.openRooms,
			]
		},
	},

	watch: {
	},

	beforeMount() {
		this.getMyRooms()
	},

	methods: {
		getMyRooms() {
			const url = generateOcsUrl('/apps/spreed/api/v4/room')
			axios.get(url).then((response) => {
				this.myRooms = response.data.ocs.data.filter((r) => r.readOnly === 0)
				console.debug('TALK rooms response', response.data.ocs.data)
			}).catch((error) => {
				console.debug(error)
			})
		},
		findOpenRooms(query) {
			this.openRooms = []
			this.query = query
			if (query === '') {
				return
			}
			this.loadingOpenRooms = true
			const url = generateOcsUrl('/apps/spreed/api/v4/listed-room')
			axios.get(url, { headers: { searchTerm: query } }).then((response) => {
				this.openRooms = response.data.ocs.data
					.filter((r) => r.readOnly === 0)
					.map((r) => { return { ...r, isOpenRoom: true } })
				console.debug('TALK OPEN rooms response', response.data.ocs.data)
			}).catch((error) => {
				console.debug(error)
			}).then(() => {
				this.loadingOpenRooms = false
			})
		},
		onSendLinkClick() {
			if (this.selectedRoom.isOpenRoom) {
				this.joinOpenRoom()
			} else {
				this.sendLink()
			}
		},
		joinOpenRoom() {
			this.loading = true
			const token = this.selectedRoom.token
			const url = generateOcsUrl('/apps/spreed/api/v4/room/{token}/participants/active', { token })
			const req = {
				force: false,
			}

			axios.post(url, req).then((response) => {
				console.debug('TALK response', response)
				showSuccess(t('integration_nuiteq', 'You joined {name}', { name: this.selectedRoom.displayName }))
				this.sendLink()
			}).catch((error) => {
				showError(
					t('integration_nuiteq', 'Failed to join')
					+ ': ' + (error.response?.data?.error ?? error.response?.request?.responseText ?? '')
				)
				console.debug(error)
				this.loading = false
			})
		},
		sendLink() {
			const token = this.selectedRoom.token
			const url = generateOcsUrl('/apps/spreed/api/v1/chat/{token}', { token })
			const req = {
				message: t('integration_nuiteq', 'NUITEQ board "{name}": {link}', {
					link: this.publicLink,
					name: this.board.name,
				}),
			}

			axios.post(url, req).then((response) => {
				console.debug('TALK response', response)
				showSuccess(t('integration_nuiteq', 'Link sent to {name}', { name: this.selectedRoom.displayName }))
				this.$emit('close')
			}).catch((error) => {
				showError(
					t('integration_nuiteq', 'Failed to send link')
					+ ': ' + (error.response?.data?.error ?? error.response?.request?.responseText ?? '')
				)
				console.debug(error)
			}).then(() => {
				this.loading = false
			})
		},
	},
}
</script>

<style scoped lang="scss">
.modal-content {
	padding: 12px;
	min-height: 300px;
	display: flex;
	flex-direction: column;

	.spacer {
		flex-grow: 1;
	}

	.multi-select {
		width: 100%;
		.option-label {
			margin-left: 8px;
		}
	}

	.modal-footer {
		width: 100%;
		display: flex;
		align-items: center;
		margin-top: 12px;

		> * {
			margin: 0 10px 0 10px;
		}

		.spacer {
			flex-grow: 1;
		}
	}
}
</style>
