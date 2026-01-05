<!--
  - SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcModal
		size="full"
		@close="$emit('close')">
		<iframe
			class="frame"
			frameborder="0"
			:allowFullScreen="true"
			:src="boardUrl" />
	</NcModal>
</template>

<script>
import NcModal from '@nextcloud/vue/components/NcModal'

export default {
	name: 'NuiteqModal',

	components: {
		NcModal,
	},

	props: {
		boardUrl: {
			type: String,
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
	},
}
</script>

<style scoped lang="scss">
.frame {
	width: 100%;
	height: 100%;
}
</style>
