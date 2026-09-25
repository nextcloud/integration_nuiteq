<!--
  - SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcAppNavigation>
		<template #list>
			<NcAppNavigationNew v-if="isConfigured"
				:text="t('integration_nuiteq', 'Create a board')"
				buttonClass="icon-add"
				@click="onCreateBoardClick">
				<template #icon>
					<PlusIcon />
				</template>
			</NcAppNavigationNew>
			<BoardNavigationItem v-for="board in boards"
				:key="board.id"
				class="boardItem"
				:board="board"
				:selected="board.id === selectedBoardId"
				@boardClicked="onBoardClicked"
				@deleteBoard="onBoardDeleted" />
		</template>
		<!--template #footer></template-->
	</NcAppNavigation>
</template>

<script>
import NcAppNavigation from '@nextcloud/vue/components/NcAppNavigation'
import NcAppNavigationNew from '@nextcloud/vue/components/NcAppNavigationNew'
import PlusIcon from 'vue-material-design-icons/Plus.vue'
import BoardNavigationItem from './BoardNavigationItem.vue'

export default {
	name: 'NuiteqNavigation',

	components: {
		BoardNavigationItem,
		NcAppNavigationNew,
		NcAppNavigation,
		PlusIcon,
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
			this.$emit('createBoardClicked')
		},

		onBoardClicked(boardId) {
			this.$emit('boardClicked', boardId)
		},

		onBoardDeleted(boardId) {
			this.$emit('deleteBoard', boardId)
		},
	},
}
</script>

<style scoped lang="scss">
.addBoardItem {
	border-bottom: 1px solid var(--color-border);
}

:deep(.boardItem) {
	padding-right: 0 !important;
	&.selectedBoard {
		> a,
		> div {
			background: var(--color-primary-light, lightgrey);
		}

		> a {
			font-weight: bold;
		}
	}
}
</style>
