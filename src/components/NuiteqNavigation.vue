<template>
	<AppNavigation>
		<template #list>
			<AppNavigationNew v-if="isConfigured"
				:text="t('integration_nuiteq', 'Create a board')"
				button-class="icon-add"
				@click="onCreateBoardClick">
				<!--template #icon>
					<PlusIcon />
				</template-->
			</AppNavigationNew>
			<BoardNavigationItem v-for="board in boards"
				:key="board.id"
				class="boardItem"
				:board="board"
				:selected="board.id === selectedBoardId"
				@board-clicked="onBoardClicked"
				@delete-board="onBoardDeleted"
				@deleting-board="onDeletingBoard" />
		</template>
		<!--template #footer></template-->
	</AppNavigation>
</template>

<script>
// import PlusIcon from 'vue-material-design-icons/Plus'
import AppNavigationNew from '@nextcloud/vue/dist/Components/AppNavigationNew'
import AppNavigation from '@nextcloud/vue/dist/Components/AppNavigation'
import BoardNavigationItem from './BoardNavigationItem'

export default {
	name: 'NuiteqNavigation',

	components: {
		BoardNavigationItem,
		AppNavigationNew,
		AppNavigation,
		// PlusIcon,
	},

	props: {
		boards: {
			type: Array,
			required: true,
		},
		selectedBoardId: {
			type: String,
			required: true,
		},
		isConfigured: {
			type: Boolean,
			required: true,
		},
	},

	data() {
		return {
		}
	},

	computed: {
	},

	watch: {
	},

	mounted() {
	},

	methods: {
		onCreateBoardClick() {
			this.$emit('create-board-clicked')
		},
		onBoardClicked(boardId) {
			this.$emit('board-clicked', boardId)
		},
		onBoardDeleted(boardId) {
			this.$emit('delete-board', boardId)
		},
		onDeletingBoard(boardId) {
			this.$emit('deleting-board', boardId)
		},
	},
}
</script>

<style scoped lang="scss">
.addBoardItem {
	border-bottom: 1px solid var(--color-border);
}

::v-deep .boardItem {
	padding-right: 0 !important;
	&.selectedBoard {
		> a,
		> div {
			background: var(--color-background-dark, lightgrey);
		}

		> a {
			font-weight: bold;
			color: var(--color-primary);
		}
	}
}
</style>
