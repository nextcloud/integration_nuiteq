<template>
	<span
		:class="{ option: true, selected: value === checked }"
		:style="{ '--border-radius': borderRadius ? borderRadius + 'px' : borderRadius }">
		<input :id="name + '-' + value"
			type="radio"
			:name="name"
			:value="value"
			@change="onUpdateValue(value)">
		<label :for="name + '-' + value">
			<span v-if="$scopedSlots.icon"
				class="option-icon">
				<slot name="icon" />
			</span>
			<span class="option-title">
				<slot />
			</span>
		</label>
	</span>
</template>

<script>
export default {
	name: 'RadioElement',

	props: {
		/**
		 * the checked value of the radio set
		 */
		checked: {
			type: String,
			required: true,
		},
		/**
		 * the actual radio value
		 */
		value: {
			type: String,
			required: true,
		},
		/**
		 * the radio set name, common to all radios in the same set
		 */
		name: {
			type: String,
			required: true,
		},
		/**
		 * border radius of the radio set corners
		 */
		borderRadius: {
			type: Number,
			default: undefined,
		},
	},

	computed: {
	},

	methods: {
		onUpdateValue(newValue) {
			this.$emit('update:checked', newValue)
		},
	},
}
</script>

<style scoped lang="scss">
.option {
	display: flex;
	align-items: center;
	min-height: 44px;
	padding: 0 14px;
	border: 2px solid var(--color-border-dark);
	// no bottom borders to avoid double borders between elements
	border-bottom: 0;
	* {
		cursor: pointer !important;
	}
	&:first-child {
		border-top-left-radius: var(--border-radius);
		border-top-right-radius: var(--border-radius);
	}
	&:last-child {
		border-bottom-left-radius: var(--border-radius);
		border-bottom-right-radius: var(--border-radius);
		// last element must have a border
		border-bottom: 2px solid var(--color-border-dark);
	}
	&:focus,
	&:hover {
		background: var(--color-primary-light);
	}
	&.selected {
		font-weight: bold;
		background: var(--color-primary-light);
		// selected element has a bottom border and we remove the one of the following element
		border: 2px solid var(--color-primary-element-light);
		&:hover {
			background: var(--color-primary-light-hover);
			border: 2px solid var(--color-primary);
		}
		& + .option {
			border-top: 0;
		}
	}
	> input {
		// display: none;
		opacity: 0;
		width: 0;
		margin: 0;
	}
	> label {
		display: flex;
		align-items: center;
		.option-icon {
			margin: 0 12px 0 0;
		}
		.option-title {
			margin: 8px 0;
		}
	}
}
</style>
