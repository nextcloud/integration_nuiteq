<!--
  - SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<span :aria-hidden="!title"
		:aria-label="title"
		class="material-design-icon talk-icon"
		role="img"
		v-bind="$attrs"
		@click="$emit('click', $event)">
		<svg :fill="fillColor"
			:width="size"
			:height="size"
			enable-background="new 0 0 16 16"
			version="1.1"
			viewBox="0 0 16 16"
			xml:space="preserve"
			xmlns="http://www.w3.org/2000/svg">
			<path d="m7.9992 0.999a6.9993 6.9994 0 0 0-6.9992 6.9996 6.9993 6.9994 0 0 0 6.9992 6.9994 6.9993 6.9994 0 0 0 3.6308-1.024c0.86024 0.34184 2.7871 1.356 3.2457 0.91794 0.47922-0.45765-0.56261-2.6116-0.81238-3.412a6.9993 6.9994 0 0 0 0.935-3.4814 6.9993 6.9994 0 0 0-6.9991-6.9993zm8e-4 2.6611a4.34 4.3401 0 0 1 4.34 4.3401 4.34 4.3401 0 0 1-4.34 4.3398 4.34 4.3401 0 0 1-4.34-4.3398 4.34 4.3401 0 0 1 4.34-4.3401z" />
		</svg>
	</span>
</template>

<script>
export default {
	name: 'TalkIcon',
	props: {
		title: {
			type: String,
			default: '',
		},
		fillColor: {
			type: String,
			default: 'currentColor',
		},
		size: {
			type: Number,
			default: 24,
		},
	},
}
</script>
