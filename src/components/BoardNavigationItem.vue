<template>
	<AppNavigationItem v-show="!deleting"
		:title="board.name"
		:class="{ selectedBoard: selected }"
		:force-menu="true"
		@click="onBoardClick">
		<template #icon>
			<ClipboardEditIcon v-if="selected"
				:size="20" />
			<ClipboardEditOutlineIcon v-else
				:size="20" />
		</template>
		<template #actions>
			<ActionButton
				:close-after-click="true"
				@click="onDeleteClick">
				<template #icon>
					<DeleteIcon :size="20" />
				</template>
				{{ t('integration_nuiteq', 'Delete') }}
			</ActionButton>
		</template>
	</AppNavigationItem>
</template>

<script>
import DeleteIcon from 'vue-material-design-icons/Delete'
import ClipboardEditIcon from 'vue-material-design-icons/ClipboardEdit'
import ClipboardEditOutlineIcon from 'vue-material-design-icons/ClipboardEditOutline'
import ClickOutside from 'vue-click-outside'

import ActionButton from '@nextcloud/vue/dist/Components/ActionButton'
import AppNavigationItem from '@nextcloud/vue/dist/Components/AppNavigationItem'
import { showUndo } from '@nextcloud/dialogs'

import { Timer } from '../utils'

export default {
	name: 'BoardNavigationItem',
	components: {
		AppNavigationItem,
		ActionButton,
		ClipboardEditIcon,
		ClipboardEditOutlineIcon,
		DeleteIcon,
	},
	directives: {
		ClickOutside,
	},
	props: {
		board: {
			type: Object,
			required: true,
		},
		selected: {
			type: Boolean,
			required: true,
		},
	},
	data() {
		return {
			deleting: false,
			deletionTimer: null,
		}
	},
	computed: {
	},
	beforeMount() {
	},
	methods: {
		onBoardClick(e) {
			this.$emit('board-clicked', this.board.id)
		},
		onDeleteClick() {
			this.deleting = true
			this.deletionTimer = new Timer(() => {
				this.$emit('delete-board', this.board.id)
			}, 10000)
			showUndo(t('integration_nuiteq', 'Board deleted'), this.cancelDeletion, { timeout: 10000 })
			this.$emit('deleting-board', this.board.id)
		},
		cancelDeletion() {
			this.deleting = false
			this.deletionTimer.pause()
			delete this.deletionTimer
		},
	},
}
</script>

<style scoped lang="scss">
// nothing
</style>
