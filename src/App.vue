<template>
	<Content app-name="integration_nuiteq">
		<NuiteqNavigation
			:boards="activeBoards"
			:selected-board-id="selectedBoardId"
			:is-configured="connected"
			@create-board-clicked="onCreateBoardClick"
			@board-clicked="onBoardClicked"
			@delete-board="onBoardDeleted"
			@deleting-board="onDeletingBoard" />
		<AppContent
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
			<EmptyContent v-else-if="!connected">
				<template #icon>
					<CogIcon />
				</template>
				{{ t('integration_nuiteq', 'Application is not configured') }}
				<!--a :href="configureUrl">
					<Button
						class="configureButton">
						<template #icon>
							<CogIcon />
						</template>
						{{ t('integration_nuiteq', 'Configure Nuiteq integration') }}
					</Button>
				</a-->
			</EmptyContent>
			<PersonalSettings v-if="!connected"
				class="settings"
				:show-title="false"
				@connected="onConnected" />
			<EmptyContent v-else-if="activeBoardCount === 0">
				<template #icon>
					<NuiteqIcon />
				</template>
				<span class="emptyContentWrapper">
					<span>
						{{ t('integration_nuiteq', 'You haven\'t created any boards yet') }}
					</span>
					<Button
						class="createButton"
						@click="onCreateBoardClick">
						<template #icon>
							<PlusIcon />
						</template>
						{{ t('integration_nuiteq', 'Create a board') }}
					</Button>
				</span>
			</EmptyContent>
			<EmptyContent v-else>
				<template #icon>
					<NuiteqIcon />
				</template>
				{{ t('integration_nuiteq', 'No selected board') }}
			</EmptyContent>
		</AppContent>
		<Modal v-if="creationModalOpen"
			size="normal"
			@close="closeCreationModal">
			<CreationForm
				:loading="creating"
				@ok-clicked="onCreationValidate"
				@cancel-clicked="closeCreationModal" />
		</Modal>
	</Content>
</template>

<script>
import CogIcon from 'vue-material-design-icons/Cog'
import PlusIcon from 'vue-material-design-icons/Plus'
import Button from '@nextcloud/vue/dist/Components/Button'
import AppContent from '@nextcloud/vue/dist/Components/AppContent'
import Content from '@nextcloud/vue/dist/Components/Content'
import Modal from '@nextcloud/vue/dist/Components/Modal'
import EmptyContent from '@nextcloud/vue/dist/Components/EmptyContent'

import { generateUrl } from '@nextcloud/router'
import { loadState } from '@nextcloud/initial-state'
import axios from '@nextcloud/axios'
import { showSuccess, showError } from '@nextcloud/dialogs'

import NuiteqNavigation from './components/NuiteqNavigation'
import CreationForm from './components/CreationForm'
import BoardDetails from './components/BoardDetails'
import NuiteqIcon from './components/NuiteqIcon'
import PersonalSettings from './components/PersonalSettings'

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
		AppContent,
		Content,
		Modal,
		EmptyContent,
		Button,
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
			return this.state.base_url && this.state.user_name && this.state.api_key
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
		onConnected() {
			window.location.reload()
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
					t('integration_nuiteq', 'Failed create new board')
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
		onBoardDeleted(boardId) {
			console.debug('DELETE board', boardId)
			// this.$delete(this.boards, boardId)
		},
		onDeletingBoard(boardId) {
			if (boardId === this.selectedBoardId) {
				this.selectedBoardId = ''
			}
		},
	},
}
</script>

<style scoped lang="scss">
::v-deep #content-vue {
	padding-top: 0 !important;
}
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
