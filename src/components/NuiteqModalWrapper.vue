<template>
	<div id="nuiteqModalWrapper">
		<NuiteqModal v-if="open && roomUrl"
			:board-url="roomUrl"
			@close="onClose" />
	</div>
</template>

<script>
import NuiteqModal from './NuiteqModal'

export default {
	name: 'NuiteqModalWrapper',

	components: {
		NuiteqModal,
	},

	props: {
	},

	data() {
		return {
			roomUrl: null,
			open: false,
		}
	},

	computed: {
	},

	watch: {
	},

	mounted() {
	},

	methods: {
		openOn(roomUrl) {
			this.roomUrl = roomUrl
			this.open = true
		},
		onClose() {
			this.open = false
			this.$emit('close')
		},
	},
}
</script>

<style scoped lang="scss">
// nothing yet
</style>
