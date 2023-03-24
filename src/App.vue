<template>
	<NcContent app-name="integration_nuiteq">
		<NuiteqNavigation
			:boards="activeBoards"
			:selected-board-id="selectedBoardId"
			:is-configured="connected"
			@create-board-clicked="onCreateBoardClick"
			@board-clicked="onBoardClicked"
			@delete-board="onBoardDeleted" />
		<NcAppContent
			:list-max-width="50"
			:list-min-width="20"
			:list-size="20"
			:show-details="false"
			@update:showDetails="a = 2">
			<!--template slot="list">
			</template-->
			<BoardDetails v-if="selectedBoard"
				:board="selectedBoard"
				:nuiteq-url="state.base_url"
				:talk-enabled="state.talk_enabled" />
			<div v-else-if="!connected">
				<NcEmptyContent
					:title="t('integration_nuiteq', 'You are not connected to NUITEQ Stage')">
					<template #icon>
						<CogIcon />
					</template>
				</NcEmptyContent>
				<PersonalSettings
					class="settings"
					:show-title="false"
					@connected="onConnected" />
			</div>
			<NcEmptyContent v-else-if="activeBoardCount === 0"
				:title="t('integration_nuiteq', 'You haven\'t created any boards yet')">
				<template #icon>
					<NuiteqIcon />
				</template>
				<template #action>
					<span class="emptyContentWrapper">
						<NcButton
							class="createButton"
							@click="onCreateBoardClick">
							<template #icon>
								<PlusIcon />
							</template>
							{{ t('integration_nuiteq', 'Create a board') }}
						</NcButton>
					</span>
				</template>
			</NcEmptyContent>
			<NcEmptyContent v-else
				:title="t('integration_nuiteq', 'No selected board')">
				<template #icon>
					<NuiteqIcon />
				</template>
			</NcEmptyContent>
		</NcAppContent>
		<NcModal v-if="creationModalOpen"
			size="small"
			@close="closeCreationModal">
			<CreationForm
				:loading="creating"
				focus-on-field="name"
				@ok-clicked="onCreationValidate"
				@cancel-clicked="closeCreationModal" />
		</NcModal>
	</NcContent>
</template>

<script>
import CogIcon from 'vue-material-design-icons/Cog.vue'
import PlusIcon from 'vue-material-design-icons/Plus.vue'

import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcAppContent from '@nextcloud/vue/dist/Components/NcAppContent.js'
import NcContent from '@nextcloud/vue/dist/Components/NcContent.js'
import NcModal from '@nextcloud/vue/dist/Components/NcModal.js'
import NcEmptyContent from '@nextcloud/vue/dist/Components/NcEmptyContent.js'

import { generateUrl } from '@nextcloud/router'
import { loadState } from '@nextcloud/initial-state'
import axios from '@nextcloud/axios'
import { showSuccess, showError, showUndo } from '@nextcloud/dialogs'

import NuiteqNavigation from './components/NuiteqNavigation.vue'
import CreationForm from './components/CreationForm.vue'
import BoardDetails from './components/BoardDetails.vue'
import NuiteqIcon from './components/icons/NuiteqIcon.vue'
import PersonalSettings from './components/PersonalSettings.vue'
import { Timer } from './utils.js'

export default {
	name: 'App',

	components: {
		PersonalSettings,
		NuiteqIcon,
		CreationForm,
		BoardDetails,
		NuiteqNavigation,
		CogIcon,
		PlusIcon,
		NcAppContent,
		NcContent,
		NcModal,
		NcEmptyContent,
		NcButton,
	},

	props: {
	},

	data() {
		return {
			creationModalOpen: false,
			selectedBoardId: '',
			state: loadState('integration_nuiteq', 'nuiteq-state'),
			configureUrl: generateUrl('/settings/user/connected-accounts'),
			creating: false,
		}
	},

	computed: {
		connected() {
			return !!this.state.base_url && !!this.state.user_name && !!this.state.api_key
		},
		activeBoards() {
			return this.state.board_list.filter((b) => !b.trash)
		},
		activeBoardsById() {
			return this.activeBoards.reduce((object, item) => {
				object[item.id] = item
				return object
			}, {})
		},
		activeBoardCount() {
			return this.activeBoards.length
		},
		selectedBoard() {
			return this.selectedBoardId
				? this.activeBoardsById[this.selectedBoardId]
				: null
		},
	},

	watch: {
	},

	beforeMount() {
		console.debug('state', this.state)
	},

	mounted() {
	},

	methods: {
		onConnected(userName, baseUrl) {
			this.state.base_url = baseUrl
			this.state.user_name = userName
			this.state.api_key = true
			// window.location.reload()
			this.getBoards()
		},
		getBoards() {
			const url = generateUrl('/apps/integration_nuiteq/list')
			axios.get(url).then((response) => {
				this.state.board_list.push(...response.data)
			}).catch((error) => {
				showError(
					t('integration_nuiteq', 'Failed to get boards')
					+ ': ' + (error.response?.data?.error ?? error.response?.request?.responseText ?? '')
				)
				console.debug(error)
			}).then(() => {
			})
		},
		onCreateBoardClick() {
			this.creationModalOpen = true
		},
		closeCreationModal() {
			this.creationModalOpen = false
		},
		onCreationValidate(board) {
			this.creating = true
			board.trash = false
			const req = {
				name: board.name,
				password: board.password,
			}
			const url = generateUrl('/apps/integration_nuiteq/new')
			axios.post(url, req).then((response) => {
				showSuccess(t('integration_nuiteq', 'New board was created in NUITEQ stage'))
				board.id = response.data?.id
				this.state.board_list.push(board)
				this.selectedBoardId = board.id
				this.creationModalOpen = false
			}).catch((error) => {
				showError(
					t('integration_nuiteq', 'Failed to create new board')
					+ ': ' + (error.response?.data?.error ?? error.response?.request?.responseText ?? '')
				)
				console.debug(error)
			}).then(() => {
				this.creating = false
			})
		},
		onBoardClicked(boardId) {
			console.debug('select board', boardId)
			this.selectedBoardId = boardId
		},
		deleteBoard(boardId) {
			console.debug('DELETE board', boardId)
			const req = {
				boardId,
			}
			const url = generateUrl('/apps/integration_nuiteq/delete')
			axios.post(url, req).then((response) => {
			}).catch((error) => {
				showError(
					t('integration_nuiteq', 'Failed to delete the board')
					+ ': ' + (error.response?.data?.error ?? error.response?.request?.responseText ?? '')
				)
				console.debug(error)
			})
		},
		onBoardDeleted(boardId) {
			// deselect the board
			if (boardId === this.selectedBoardId) {
				this.selectedBoardId = ''
			}

			// hide the board nav item
			const boardIndex = this.state.board_list.findIndex((b) => b.id === boardId)
			const board = this.state.board_list[boardIndex]
			if (boardIndex !== -1) {
				board.trash = true
			}

			// cancel or delete
			const deletionTimer = new Timer(() => {
				this.deleteBoard(boardId)
			}, 10000)
			showUndo(
				t('integration_nuiteq', '{name} deleted', { name: board.name }),
				() => {
					deletionTimer.pause()
					board.trash = false
				},
				{ timeout: 10000 }
			)
		},
	},
}
</script>

<style scoped lang="scss">
// TODO in global css loaded by main
body {
	min-height: 100%;
	height: auto;
}

.settings {
	display: flex;
	flex-direction: column;
	align-items: center;
}

.emptyContentWrapper {
	display: flex;
	flex-direction: column;
	align-items: center;
}

.createButton,
.configureButton {
	margin-top: 12px;
}
</style>
