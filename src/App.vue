<!--
  - SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcContent appName="integration_nuiteq">
		<NuiteqNavigation
			:boards="activeBoards"
			:selectedBoardId="selectedBoardId"
			:isConfigured="connected"
			@createBoardClicked="onCreateBoardClick"
			@boardClicked="onBoardClicked"
			@deleteBoard="onBoardDeleted" />
		<NcAppContent
			:listMaxWidth="50"
			:listMinWidth="20"
			:listSize="20"
			:showDetails="false"
			@update:showDetails="a = 2">
			<!--template slot="list">
			</template-->
			<BoardDetails v-if="selectedBoard"
				:key="selectedBoard.id"
				:board="selectedBoard"
				:nuiteqUrl="state.base_url"
				:talkEnabled="state.talk_enabled" />
			<div v-else-if="!connected">
				<NcEmptyContent
					:name="t('integration_nuiteq', 'You are not connected to NUITEQ Stage')">
					<template #icon>
						<CogIcon />
					</template>
				</NcEmptyContent>
				<PersonalSettings
					class="settings"
					:showTitle="false"
					@connected="onConnected" />
			</div>
			<NcEmptyContent v-else-if="activeBoardCount === 0"
				:name="t('integration_nuiteq', 'You haven\'t created any boards yet')">
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
				:name="t('integration_nuiteq', 'No selected board')">
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
				focusOnField="name"
				@okClicked="onCreationValidate"
				@cancelClicked="closeCreationModal" />
		</NcModal>
	</NcContent>
</template>

<script>
import axios from '@nextcloud/axios'
import { showError, showSuccess, showUndo } from '@nextcloud/dialogs'
import { loadState } from '@nextcloud/initial-state'
import { generateUrl } from '@nextcloud/router'
import NcAppContent from '@nextcloud/vue/components/NcAppContent'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcContent from '@nextcloud/vue/components/NcContent'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import NcModal from '@nextcloud/vue/components/NcModal'
import CogIcon from 'vue-material-design-icons/Cog.vue'
import PlusIcon from 'vue-material-design-icons/Plus.vue'
import BoardDetails from './components/BoardDetails.vue'
import CreationForm from './components/CreationForm.vue'
import NuiteqIcon from './components/icons/NuiteqIcon.vue'
import NuiteqNavigation from './components/NuiteqNavigation.vue'
import PersonalSettings from './components/PersonalSettings.vue'
import { failureReason, Timer } from './utils.js'

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
			return Array.isArray(this.state.board_list)
				? this.state.board_list.filter((b) => !b.trash)
				: []
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
			return axios.get(url).then((response) => {
				if (!Array.isArray(response.data)) {
					throw new TypeError(t('integration_nuiteq', 'NUITEQ did not answer with a list of boards'))
				}
				this.state.board_list = response.data
			}).catch((error) => {
				showError(t('integration_nuiteq', 'Failed to get boards') + ': ' + failureReason(error, t))
				console.debug(error)
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
			return axios.post(url, req).then((response) => {
				const id = response.data?.id
				if (typeof id !== 'string' || id === '') {
					// the board would sit in the list with no id, which cannot
					// be opened, deleted or linked to
					throw new TypeError(t('integration_nuiteq', 'NUITEQ did not say which board it created'))
				}
				showSuccess(t('integration_nuiteq', 'New board was created in NUITEQ stage'))
				board.id = id
				this.state.board_list = [
					...(Array.isArray(this.state.board_list) ? this.state.board_list : []),
					board,
				]
				this.selectedBoardId = board.id
				this.creationModalOpen = false
			}).catch((error) => {
				showError(t('integration_nuiteq', 'Failed to create new board') + ': ' + failureReason(error, t))
				console.debug(error)
			}).then(() => {
				this.creating = false
			})
		},

		onBoardClicked(boardId) {
			console.debug('select board', boardId)
			this.selectedBoardId = boardId
		},

		deleteBoard(boardId, wasSelected) {
			console.debug('DELETE board', boardId)
			const req = {
				boardId,
			}
			const url = generateUrl('/apps/integration_nuiteq/delete')
			axios.post(url, req).then(() => {
			}).catch((error) => {
				// the board was hidden before the request: put it back, or the
				// user loses sight of a board the server still has
				const board = this.state.board_list.find((b) => b.id === boardId)
				if (board) {
					board.trash = false
					if (wasSelected && this.selectedBoardId === '') {
						// only the deletion that deselected it may select it
						// again, and only if no board is open by then
						this.selectedBoardId = boardId
					}
				}
				showError(t('integration_nuiteq', 'Failed to delete the board') + ': ' + failureReason(error, t))
				console.debug(error)
			})
		},

		onBoardDeleted(boardId) {
			// deselect the board
			const wasSelected = boardId === this.selectedBoardId
			if (wasSelected) {
				this.selectedBoardId = ''
			}

			// hide the board nav item
			const board = this.state.board_list.find((b) => b.id === boardId)
			if (board === undefined) {
				return
			}
			board.trash = true

			// cancel or delete
			let deleted = false
			const deletionTimer = new Timer(() => {
				deleted = true
				this.deleteBoard(boardId, wasSelected)
			}, 10000)
			showUndo(
				t('integration_nuiteq', '{name} deleted', { name: board.name }),
				() => {
					if (deleted) {
						// the toast outlived the deletion: pausing an expired
						// timer does nothing, and the board is gone on NUITEQ
						return
					}
					deletionTimer.pause()
					board.trash = false
					if (wasSelected && this.selectedBoardId === '') {
						this.selectedBoardId = boardId
					}
				},
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
