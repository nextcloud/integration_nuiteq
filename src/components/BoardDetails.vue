<template>
	<div class="boardDetails">
		<h2>
			{{ board.name }}
		</h2>
		<div class="links">
			<div class="link">
				<LinkVariantIcon :size="20" />
				<label>
					{{ t('integration_nuiteq', 'Public board link') }}
				</label>
				<div class="linkInputWrapper">
					<input type="text" :readonly="true" :value="publicLink">
					<a :href="publicLink" @click.prevent.stop="copyLink(false)">
						<Button v-tooltip.bottom="{ content: t('integration_nuiteq', 'Copy to clipboard') }">
							<template #icon>
								<ClipboardCheckOutlineIcon v-if="publicLinkCopied"
									class="copiedIcon"
									:size="20" />
								<ClipboardArrowLeftOutlineIcon v-else
									:size="20" />
							</template>
						</Button>
					</a>
				</div>
			</div>
			<div class="link">
				<ShieldLinkVariantIcon :size="20" />
				<label>
					{{ t('integration_nuiteq', 'Admin board link') }}
				</label>
				<div class="linkInputWrapper">
					<input type="text" :readonly="true" :value="adminLink">
					<a :href="adminLink" @click.prevent.stop="copyLink(true)">
						<Button v-tooltip.bottom="{ content: t('integration_nuiteq', 'Copy to clipboard') }">
							<template #icon>
								<ClipboardCheckOutlineIcon v-if="adminLinkCopied"
									class="copiedIcon"
									:size="20" />
								<ClipboardArrowLeftOutlineIcon v-else
									:size="20" />
							</template>
						</Button>
					</a>
				</div>
			</div>
		</div>
		<div class="fields">
			<div v-for="(field, fieldId) in fields"
				:key="fieldId"
				class="field">
				<component :is="field.icon"
					v-if="field.icon"
					:size="20" />
				<span v-else class="emptyIcon" />
				<label class="fieldLabel">
					{{ field.label }}
				</label>
				<label v-if="['ncCheckbox'].includes(field.type)"
					:id="'board-' + fieldId + '-value'"
					class="fieldValue multiple">
					<component :is="field.enabledIcon"
						v-if="board[fieldId] && field.enabledIcon"
						:size="20" />
					<component :is="field.disabledIcon"
						v-else-if="!board[fieldId] && field.disabledIcon"
						:size="20" />
					<CheckboxMarkedIcon v-else-if="board[fieldId]" :size="20" />
					<CheckboxBlankOutlineIcon v-else-if="!board[fieldId]" :size="20" />
					{{ board[fieldId] ? t('integration_nuiteq', 'Enabled') : t('integration_nuiteq', 'Disabled') }}
				</label>
				<label v-if="['ncSwitch'].includes(field.type)"
					:id="'board-' + fieldId + '-value'"
					class="fieldValue multiple">
					<component :is="field.enabledIcon"
						v-if="board[fieldId] && field.enabledIcon"
						:size="20" />
					<component :is="field.disabledIcon"
						v-else-if="!board[fieldId] && field.disabledIcon"
						:size="20" />
					<ToggleSwitchIcon v-else-if="board[fieldId]" :size="20" />
					<ToggleSwitchOffOutlineIcon v-else-if="!board[fieldId]" :size="20" />
					{{ board[fieldId] ? t('integration_nuiteq', 'Enabled') : t('integration_nuiteq', 'Disabled') }}
				</label>
				<label v-if="['text'].includes(field.type)"
					:id="'board-' + fieldId + '-value'"
					class="fieldValue">
					{{ board[fieldId] }}
				</label>
				<label v-else-if="['ncDate'].includes(field.type)"
					:id="'board-' + fieldId + '-value'"
					class="fieldValue">
					{{ getFormattedDate(board[fieldId]) }}
				</label>
				<label v-else-if="['ncDatetime'].includes(field.type)"
					:id="'board-' + fieldId + '-value'"
					class="fieldValue">
					{{ getFormattedDatetime(board[fieldId]) }}
				</label>
				<label v-else-if="['ncColor'].includes(field.type)"
					:id="'board-' + fieldId + '-value'"
					class="fieldValue">
					<div class="colorDot" :style="{ 'background-color': board[fieldId] }" />
				</label>
				<textarea v-if="['textarea'].includes(field.type)"
					:id="'board-' + fieldId + '-value'"
					class="fieldValue"
					:value="board[fieldId]"
					:readonly="true" />
				<label v-else-if="['select', 'customRadioSet', 'ncRadioSet'].includes(field.type)"
					:for="'board-' + fieldId + '-value'"
					class="fieldValue multiple">
					<component :is="field.options[board[fieldId]].icon"
						v-if="field.options[board[fieldId]].icon"
						:size="20" />
					{{ field.options[board[fieldId]].label }}
				</label>
				<label v-else-if="['ncCheckboxSet'].includes(field.type)"
					:for="'board-' + fieldId + '-value'"
					class="fieldValue multipleVertical">
					<div v-for="optionId in board[fieldId]"
						:key="optionId"
						class="oneValue">
						<component :is="field.options[optionId].icon"
							v-if="field.options[optionId].icon"
							:size="20" />
						{{ field.options[optionId].label }}
					</div>
				</label>
			</div>
		</div>
	</div>
</template>

<script>
import { fields, Timer } from '../utils'
import moment from '@nextcloud/moment'
import ShieldLinkVariantIcon from 'vue-material-design-icons/ShieldLinkVariant'
import LinkVariantIcon from 'vue-material-design-icons/LinkVariant'
import ClipboardArrowLeftOutlineIcon from 'vue-material-design-icons/ClipboardArrowLeftOutline'
import ClipboardCheckOutlineIcon from 'vue-material-design-icons/ClipboardCheckOutline'
import ToggleSwitchIcon from 'vue-material-design-icons/ToggleSwitch'
import ToggleSwitchOffOutlineIcon from 'vue-material-design-icons/ToggleSwitchOffOutline'
import CheckboxMarkedIcon from 'vue-material-design-icons/CheckboxMarked'
import CheckboxBlankOutlineIcon from 'vue-material-design-icons/CheckboxBlankOutline'

import Button from '@nextcloud/vue/dist/Components/Button'
import { showSuccess, showError } from '@nextcloud/dialogs'

export default {
	name: 'BoardDetails',

	components: {
		LinkVariantIcon,
		ShieldLinkVariantIcon,
		ClipboardArrowLeftOutlineIcon,
		ClipboardCheckOutlineIcon,
		ToggleSwitchIcon,
		ToggleSwitchOffOutlineIcon,
		CheckboxBlankOutlineIcon,
		CheckboxMarkedIcon,
		Button,
	},

	props: {
		board: {
			type: Object,
			required: true,
		},
	},

	data() {
		return {
			fields,
			adminLinkCopied: false,
			publicLinkCopied: false,
		}
	},

	computed: {
		publicLink() {
			return 'https://nuiteqstage.se/board/PUBLIC_TOKEN'
		},
		adminLink() {
			return 'https://nuiteqstage.se/board/ADMIN_TOKEN'
		},
	},

	watch: {
	},

	mounted() {
	},

	methods: {
		async copyLink(admin = false) {
			const link = admin
				? this.adminLink
				: this.publicLink
			try {
				await this.$copyText(link)
				if (admin) {
					this.adminLinkCopied = true
					showSuccess(t('integration_nuiteq', 'Admin link copied!'))
				} else {
					this.publicLinkCopied = true
					showSuccess(t('integration_nuiteq', 'Public link copied!'))
				}
				// eslint-disable-next-line
				new Timer(() => {
					if (admin) {
						this.adminLinkCopied = false
					} else {
						this.publicLinkCopied = false
					}
				}, 5000)
			} catch (error) {
				console.error(error)
				showError(t('integration_nuiteq', 'Link could not be copied to clipboard'))
			}
		},
		getFormattedDate(date) {
			return moment(date).format('LL')
		},
		getFormattedDatetime(date) {
			return moment(date).format('LLL')
		},
	},
}
</script>

<style scoped lang="scss">
.boardDetails {
	display: flex;
	flex-direction: column;
	align-items: center;
	justify-content: center;
	h2 {
		margin: 12px 0 32px 0;
	}
	.fields {
		display: flex;
		flex-direction: column;
		.field {
			display: flex;
			align-items: center;
			margin: 8px 0;
			padding: 8px;
			border-radius: var(--border-radius);
			&:hover {
				background-color: var(--color-background-hover);
			}
			> * {
				margin: 0 8px 0 8px;
			}
			.emptyIcon {
				width: 20px;
			}
			.fieldLabel {
				width: 250px;
			}
			.fieldValue {
				&.multiple {
					display: flex;
					> * {
						margin-right: 8px;
					}
				}
				&.multipleVertical {
					display: flex;
					flex-direction: column;
					.oneValue {
						display: flex;
						> * {
							margin-right: 8px;
						}
					}
				}
			}
			textarea.fieldValue {
				width: 300px;
				height: 65px;
				resize: none;
			}
			.colorDot {
				width: 24px;
				height: 24px;
				border-radius: 50%;
			}
		}
	}
	.links {
		display: flex;
		flex-direction: column;
		.link {
			display: flex;
			align-items: center;
			margin: 6px 0 6px 0;
			> * {
				margin: 0 8px 0 8px;
			}
			label {
				width: 250px;
			}
			.linkInputWrapper {
				display: flex;
				width: 300px;
				input {
					flex-grow: 1;
				}
			}
			.copiedIcon {
				color: var(--color-success);
			}
		}
	}
}
</style>
