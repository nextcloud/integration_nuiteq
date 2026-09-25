<!--
  - SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcAppNavigationItem
		:title="board.name"
		:class="{ selectedBoard: selected }"
		:forceMenu="true"
		@click="onBoardClick">
		<template #icon>
			<ClipboardEditIcon v-if="selected"
				:size="20" />
			<ClipboardEditOutlineIcon v-else
				:size="20" />
		</template>
		<template #actions>
			<NcActionButton
				:closeAfterClick="true"
				@click="onDeleteClick">
				<template #icon>
					<DeleteIcon :size="20" />
				</template>
				{{ t('integration_nuiteq', 'Delete') }}
			</NcActionButton>
		</template>
	</NcAppNavigationItem>
</template>

<script>
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import NcAppNavigationItem from '@nextcloud/vue/components/NcAppNavigationItem'
import ClipboardEditIcon from 'vue-material-design-icons/ClipboardEdit.vue'
import ClipboardEditOutlineIcon from 'vue-material-design-icons/ClipboardEditOutline.vue'
import DeleteIcon from 'vue-material-design-icons/Delete.vue'

export default {
	name: 'BoardNavigationItem',
	components: {
		NcAppNavigationItem,
		NcActionButton,
		ClipboardEditIcon,
		ClipboardEditOutlineIcon,
		DeleteIcon,
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
		}
	},

	computed: {
	},

	beforeMount() {
	},

	methods: {
		onBoardClick() {
			this.$emit('boardClicked', this.board.id)
		},

		onDeleteClick() {
			this.$emit('deleteBoard', this.board.id)
		},
	},
}
</script>

<style scoped lang="scss">
// nothing
</style>
