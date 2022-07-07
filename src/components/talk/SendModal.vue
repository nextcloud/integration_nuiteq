<template>
	<Modal
		size="normal"
		@close="$emit('close')">
		<div class="modal-content">
			<h2>
				{{ t('integration_nuiteq', 'Send link to a Talk room') }}
			</h2>
			<Multiselect
				v-model="selectedRoom"
				class="multi-select"
				:placeholder="t('integration_nuiteq', 'Search for users, groups or rooms')"
				:options="rooms"
				:user-select="false"
				label="displayName"
				track-by="token"
				:loading="loadingOpenRooms || loadingUsers"
				:internal-search="true"
				@search-change="searchQueryChanged">
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
				<div class="spacer" />
				<Button @click="$emit('close')">
					{{ t('integration_nuiteq', 'Cancel') }}
				</Button>
				<Button type="primary"
					:disabled="selectedRoom === null"
					@click="onSendLinkClick">
					<template #icon>
						<SendIcon :class="{ 'icon-loading': sending }" />
					</template>
					{{ t('integration_nuiteq', 'Send') }}
				</Button>
			</div>
		</div>
	</Modal>
</template>

<script>

import SendIcon from 'vue-material-design-icons/Send'
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
			users: [],
			loadingOpenRooms: false,
			loadingUsers: false,
			selectedRoom: null,
			sending: false,
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
				...this.users.filter((u) => {
					// avoid users for which we already have a 1-1 conversation
					return !this.myRooms.find((r) => r.type === 1 && u.id === r.name)
				}),
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
		searchQueryChanged(query) {
			this.query = query
			this.findOpenRooms(query)
			this.findUsers(query)
		},
		findUsers(query) {
			this.users = []
			if (query === '') {
				return
			}
			this.loadingUsers = true
			const url = generateOcsUrl('core/autocomplete/get', 2).replace(/\/$/, '')
			axios.get(url, {
				params: {
					format: 'json',
					search: query,
					itemType: ' ',
					itemId: ' ',
					shareTypes: [0],
				},
			}).then((response) => {
				this.users = response.data.ocs.data.map((u) => {
					return {
						id: u.id,
						name: u.id,
						displayName: u.label,
						type: 1,
						isUser: true,
					}
				})
			}).catch((error) => {
				console.error(error)
			}).then(() => {
				this.loadingUsers = false
			})
		},
		findOpenRooms(query) {
			this.openRooms = []
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
			} else if (this.selectedRoom.isUser) {
				this.createOneToOneRoom()
			} else {
				this.sendLink()
			}
		},
		createOneToOneRoom() {
			this.sending = true
			const userId = this.selectedRoom.id
			const url = generateOcsUrl('/apps/spreed/api/v4/room')
			const req = {
				roomType: 1,
				invite: userId,
			}

			axios.post(url, req).then((response) => {
				console.debug('TALK create room response', response.data.ocs.data)
				showSuccess(t('integration_nuiteq', 'You invite {name} in a 1-1 conversation', { name: this.selectedRoom.displayName }))
				this.selectedRoom.token = response.data.ocs.data.token
				this.sendLink()
			}).catch((error) => {
				showError(
					t('integration_nuiteq', 'Failed to join')
					+ ': ' + (error.response?.data?.error ?? error.response?.request?.responseText ?? '')
				)
				console.debug(error)
				this.sending = false
			})
		},
		joinOpenRoom() {
			this.sending = true
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
				this.sending = false
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
				this.sending = false
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

	h2 {
		text-align: center;
	}

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
			margin-left: 8px;
		}
	}
}
</style>
