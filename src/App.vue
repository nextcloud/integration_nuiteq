<template>
	<Content app-name="integration_nuiteq">
		<NuiteqNavigation
			:boards="boards"
			:selected-board-id="selectedBoardId"
			:is-configured="state.is_configured"
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
				:board="selectedBoard" />
			<EmptyContent v-else-if="!state.is_configured">
				<template #icon>
					<CogIcon />
				</template>
				{{ t('integration_nuiteq', 'Application is not configured') }}
				<a v-if="currentUser.isAdmin"
					:href="configureUrl">
					<Button
						class="configureButton">
						<template #icon>
							<CogIcon />
						</template>
						{{ t('integration_nuiteq', 'Configure Nuiteq integration') }}
					</Button>
				</a>
				<p v-else>
					{{ t('integration_nuiteq', 'Ask your administrator to configure this integration') }}
				</p>
			</EmptyContent>
			<EmptyContent v-else-if="boardCount === 0">
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
import { getCurrentUser } from '@nextcloud/auth'

import NuiteqNavigation from './components/NuiteqNavigation'
import CreationForm from './components/CreationForm'
import BoardDetails from './components/BoardDetails'
import NuiteqIcon from './components/NuiteqIcon'

export default {
	name: 'App',

	components: {
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
			boards: {},
			selectedBoardId: 0,
			state: loadState('integration_nuiteq', 'page-state'),
			currentUser: getCurrentUser(),
			configureUrl: generateUrl('/settings/admin/connected-accounts'),
		}
	},

	computed: {
		selectedBoard() {
			return this.boards[this.selectedBoardId]
		},
		boardCount() {
			return Object.keys(this.boards).length
		},
	},

	watch: {
	},

	mounted() {
	},

	methods: {
		onCreateBoardClick() {
			this.creationModalOpen = true
		},
		closeCreationModal() {
			this.creationModalOpen = false
		},
		onCreationValidate(board) {
			console.debug('CREATE', board)
			this.creationModalOpen = false
			const nbBoards = Object.values(this.boards).length
			board.id = nbBoards + 1
			this.$set(this.boards, board.id, board)
			console.debug(this.boards)
			this.selectedBoardId = board.id
		},
		onBoardClicked(boardId) {
			console.debug('select board', boardId)
			this.selectedBoardId = boardId
		},
		onBoardDeleted(boardId) {
			console.debug('DELETE board', boardId)
			this.$delete(this.boards, boardId)
		},
		onDeletingBoard(boardId) {
			if (boardId === this.selectedBoardId) {
				this.selectedBoardId = 0
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
