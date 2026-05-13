<?php
/**
 * Alpine.js editor for comparison tables.
 *
 * Layout: caption, items header strip (cards), badge controls (visible when
 * an item is highlighted), then a feature rows section where each row has
 * a label/description/tooltip block and one cell per item. Each cell is a
 * three-state picker (available / unavailable / custom); custom expands an
 * inline rich content sub-editor that mirrors Session 4's general cell.
 *
 * @package KDNA_Tables
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div
	class="kdna-editor kdna-editor--comparison"
	x-data="kdnaTablesComparisonEditor()"
	x-init="init()"
	x-cloak
>
	<!-- Caption -->
	<div class="kdna-editor__header">
		<div class="kdna-editor__field">
			<label class="kdna-editor__label" for="kdna-editor-caption">
				<?php esc_html_e( 'Caption', 'kdna-tables' ); ?>
			</label>
			<input
				id="kdna-editor-caption"
				class="kdna-editor__caption-input"
				type="text"
				x-model="state.caption"
				placeholder="<?php esc_attr_e( 'Optional table caption', 'kdna-tables' ); ?>"
			/>
		</div>
	</div>

	<!-- Items header strip -->
	<section class="kdna-comparison-editor__section">
		<header class="kdna-comparison-editor__section-header">
			<h3><?php esc_html_e( 'Items', 'kdna-tables' ); ?></h3>
			<button
				type="button"
				class="button button-secondary"
				:disabled="state.comparison.items.length >= maxItems"
				@click="addItem()"
			><?php esc_html_e( '+ Item', 'kdna-tables' ); ?></button>
		</header>

		<div class="kdna-comparison-editor__items">
			<template x-for="(item, itemIdx) in state.comparison.items" :key="item.id">
				<div
					class="kdna-comparison-editor__item-card"
					:class="isHighlighted(itemIdx) ? 'is-highlighted' : ''"
				>
					<!-- Image -->
					<div class="kdna-comparison-editor__item-image">
						<button
							type="button"
							class="kdna-comparison-editor__item-image-button"
							@click="openItemImagePicker(itemIdx)"
							:aria-label="`<?php echo esc_js( __( 'Set item image', 'kdna-tables' ) ); ?>`"
						>
							<template x-if="item.image.url">
								<img :src="item.image.url" :alt="item.image.alt || ''" />
							</template>
							<template x-if="!item.image.url">
								<span class="kdna-comparison-editor__item-image-placeholder">
									<?php esc_html_e( '+ Image', 'kdna-tables' ); ?>
								</span>
							</template>
						</button>
						<button
							type="button"
							class="kdna-editor__icon-button kdna-editor__icon-button--danger kdna-comparison-editor__item-image-remove"
							x-show="item.image.url"
							@click="removeItemImage(itemIdx)"
							:aria-label="`<?php echo esc_js( __( 'Remove item image', 'kdna-tables' ) ); ?>`"
						>&times;</button>
					</div>

					<!-- Highlight radio -->
					<label class="kdna-comparison-editor__item-highlight">
						<input
							type="radio"
							name="kdna_comparison_highlight"
							:checked="isHighlighted(itemIdx)"
							@click="toggleHighlight(itemIdx)"
						/>
						<span><?php esc_html_e( 'Highlight', 'kdna-tables' ); ?></span>
					</label>

					<!-- Label + sublabel -->
					<input
						type="text"
						class="kdna-comparison-editor__item-label"
						x-model="item.label"
						:placeholder="`<?php echo esc_js( __( 'Item', 'kdna-tables' ) ); ?> ${ itemIdx + 1 }`"
						:aria-label="`<?php echo esc_js( __( 'Item label', 'kdna-tables' ) ); ?> ${ itemIdx + 1 }`"
					/>
					<input
						type="text"
						class="kdna-comparison-editor__item-sublabel"
						x-model="item.sublabel"
						placeholder="<?php esc_attr_e( 'Sublabel (optional)', 'kdna-tables' ); ?>"
						:aria-label="`<?php echo esc_js( __( 'Item sublabel', 'kdna-tables' ) ); ?>`"
					/>

					<!-- CTA -->
					<label class="kdna-comparison-editor__item-cta-toggle">
						<input type="checkbox" x-model="item.cta.enabled" />
						<span><?php esc_html_e( 'Enable CTA', 'kdna-tables' ); ?></span>
					</label>
					<template x-if="item.cta.enabled">
						<div class="kdna-comparison-editor__item-cta-fields">
							<input
								type="text"
								class="kdna-comparison-editor__item-cta-text"
								x-model="item.cta.text"
								placeholder="<?php esc_attr_e( 'CTA text', 'kdna-tables' ); ?>"
								:aria-label="`<?php echo esc_js( __( 'CTA button text', 'kdna-tables' ) ); ?>`"
							/>
							<input
								type="url"
								class="kdna-comparison-editor__item-cta-url"
								x-model="item.cta.url"
								placeholder="https://example.com"
								:aria-label="`<?php echo esc_js( __( 'CTA destination URL', 'kdna-tables' ) ); ?>`"
							/>
						</div>
					</template>

					<!-- Item toolbar -->
					<div class="kdna-comparison-editor__item-toolbar">
						<button
							type="button"
							class="kdna-editor__icon-button"
							:disabled="itemIdx === 0"
							@click="moveItem(itemIdx, -1)"
							:aria-label="`<?php echo esc_js( __( 'Move item left', 'kdna-tables' ) ); ?>`"
						>&larr;</button>
						<button
							type="button"
							class="kdna-editor__icon-button"
							:disabled="itemIdx === state.comparison.items.length - 1"
							@click="moveItem(itemIdx, 1)"
							:aria-label="`<?php echo esc_js( __( 'Move item right', 'kdna-tables' ) ); ?>`"
						>&rarr;</button>
						<button
							type="button"
							class="kdna-editor__icon-button kdna-editor__icon-button--danger"
							:disabled="state.comparison.items.length <= 1"
							@click="removeItem(itemIdx)"
							:aria-label="`<?php echo esc_js( __( 'Delete item', 'kdna-tables' ) ); ?>`"
						>&times;</button>
					</div>
				</div>
			</template>
		</div>

		<!-- Badge controls -->
		<div
			class="kdna-comparison-editor__badge"
			x-show="hasHighlight()"
		>
			<div class="kdna-editor__field">
				<label class="kdna-editor__label" for="kdna-editor-badge-text">
					<?php esc_html_e( 'Badge text', 'kdna-tables' ); ?>
				</label>
				<input
					id="kdna-editor-badge-text"
					type="text"
					x-model="state.comparison.badge_text"
					placeholder="<?php esc_attr_e( 'Most Popular', 'kdna-tables' ); ?>"
				/>
			</div>
			<div class="kdna-editor__field">
				<label class="kdna-editor__label" for="kdna-editor-badge-position">
					<?php esc_html_e( 'Badge position', 'kdna-tables' ); ?>
				</label>
				<select id="kdna-editor-badge-position" x-model="state.comparison.badge_position">
					<option value="top-left"><?php esc_html_e( 'Top Left', 'kdna-tables' ); ?></option>
					<option value="top-centre"><?php esc_html_e( 'Top Centre', 'kdna-tables' ); ?></option>
					<option value="top-right"><?php esc_html_e( 'Top Right', 'kdna-tables' ); ?></option>
				</select>
			</div>
		</div>
	</section>

	<!-- Feature rows -->
	<section class="kdna-comparison-editor__section">
		<header class="kdna-comparison-editor__section-header">
			<h3><?php esc_html_e( 'Feature rows', 'kdna-tables' ); ?></h3>
			<button
				type="button"
				class="button button-secondary"
				@click="addFeatureRow()"
			><?php esc_html_e( '+ Feature row', 'kdna-tables' ); ?></button>
		</header>

		<template x-for="(row, rowIdx) in state.comparison.feature_rows" :key="row.id">
			<article
				class="kdna-comparison-editor__feature-row"
				:style="`--kdna-items: ${ state.comparison.items.length };`"
			>
				<header class="kdna-comparison-editor__feature-row-header">
					<span
						class="kdna-editor__row-label"
						x-text="`<?php echo esc_js( __( 'Feature', 'kdna-tables' ) ); ?> ${ rowIdx + 1 }`"
					></span>
					<div class="kdna-editor__row-toolbar">
						<button
							type="button"
							class="kdna-editor__icon-button"
							:disabled="rowIdx === 0"
							@click="moveFeatureRow(rowIdx, -1)"
							:aria-label="`<?php echo esc_js( __( 'Move feature row up', 'kdna-tables' ) ); ?>`"
						>&uarr;</button>
						<button
							type="button"
							class="kdna-editor__icon-button"
							:disabled="rowIdx === state.comparison.feature_rows.length - 1"
							@click="moveFeatureRow(rowIdx, 1)"
							:aria-label="`<?php echo esc_js( __( 'Move feature row down', 'kdna-tables' ) ); ?>`"
						>&darr;</button>
						<button
							type="button"
							class="kdna-editor__icon-button kdna-editor__icon-button--danger"
							@click="removeFeatureRow(rowIdx)"
							:aria-label="`<?php echo esc_js( __( 'Delete feature row', 'kdna-tables' ) ); ?>`"
						>&times;</button>
					</div>
				</header>

				<div class="kdna-comparison-editor__feature-row-grid">
					<div class="kdna-comparison-editor__feature-label">
						<input
							type="text"
							class="kdna-comparison-editor__feature-label-input"
							x-model="row.label"
							placeholder="<?php esc_attr_e( 'Feature label', 'kdna-tables' ); ?>"
						/>
						<textarea
							class="kdna-comparison-editor__feature-description"
							rows="2"
							x-model="row.description"
							placeholder="<?php esc_attr_e( 'Description (optional)', 'kdna-tables' ); ?>"
						></textarea>
						<textarea
							class="kdna-comparison-editor__feature-tooltip"
							rows="2"
							x-model="row.tooltip"
							placeholder="<?php esc_attr_e( 'Tooltip text (optional)', 'kdna-tables' ); ?>"
						></textarea>
					</div>

					<template x-for="(cell, colIdx) in row.cells" :key="`${ row.id }-${ colIdx }`">
						<div
							class="kdna-comparison-editor__feature-cell"
							:class="`is-${ cell.state }`"
						>
							<div
								class="kdna-comparison-editor__feature-cell-state"
								role="radiogroup"
								:aria-label="`<?php echo esc_js( __( 'Cell state for', 'kdna-tables' ) ); ?> ${ state.comparison.items[colIdx] ? state.comparison.items[colIdx].label : '' }`"
							>
								<button
									type="button"
									class="kdna-editor__icon-button"
									:aria-pressed="cell.state === 'available'"
									@click="setCellState(rowIdx, colIdx, 'available')"
									:aria-label="`<?php echo esc_js( __( 'Available', 'kdna-tables' ) ); ?>`"
									title="<?php echo esc_attr__( 'Available', 'kdna-tables' ); ?>"
								>&#10003;</button>
								<button
									type="button"
									class="kdna-editor__icon-button"
									:aria-pressed="cell.state === 'unavailable'"
									@click="setCellState(rowIdx, colIdx, 'unavailable')"
									:aria-label="`<?php echo esc_js( __( 'Unavailable', 'kdna-tables' ) ); ?>`"
									title="<?php echo esc_attr__( 'Unavailable', 'kdna-tables' ); ?>"
								>&times;</button>
								<button
									type="button"
									class="kdna-editor__icon-button"
									:aria-pressed="cell.state === 'custom'"
									@click="setCellState(rowIdx, colIdx, 'custom')"
									:aria-label="`<?php echo esc_js( __( 'Custom content', 'kdna-tables' ) ); ?>`"
									title="<?php echo esc_attr__( 'Custom content', 'kdna-tables' ); ?>"
								>&#9998;</button>
							</div>

							<!-- Custom sub-editor: shown only when state === 'custom'. -->
							<div
								class="kdna-comparison-editor__feature-cell-custom"
								x-show="cell.state === 'custom'"
							>
								<div class="kdna-editor__cell-preview">
									<!-- Icon piece -->
									<span
										class="kdna-editor__cell-piece kdna-editor__cell-piece--icon"
										x-show="customHasPiece(cell, 'icon')"
										:style="`order: ${ customPieceOrder(cell, 'icon') };`"
									>
										<i :class="cell.custom.icon.value" aria-hidden="true"></i>
										<button
											type="button"
											class="kdna-editor__icon-button kdna-editor__icon-button--danger kdna-editor__piece-remove"
											@click="removeCustomIcon(rowIdx, colIdx)"
											:aria-label="`<?php echo esc_js( __( 'Remove icon', 'kdna-tables' ) ); ?>`"
										>&times;</button>
									</span>
									<!-- Text piece -->
									<div
										class="kdna-editor__cell-piece kdna-editor__cell-piece--text"
										:style="`order: ${ customPieceOrder(cell, 'text') };`"
									>
										<div
											class="kdna-editor__cell-text"
											contenteditable="plaintext-only"
											role="textbox"
											spellcheck="true"
											x-init="$el.innerText = cell.custom.text"
											@input="setCustomText(rowIdx, colIdx, $event.target.innerText)"
											@keydown.enter="if (!$event.shiftKey) { $event.preventDefault(); $event.target.blur(); }"
										></div>
									</div>
									<!-- Image piece -->
									<span
										class="kdna-editor__cell-piece kdna-editor__cell-piece--image"
										x-show="customHasPiece(cell, 'image')"
										:style="`order: ${ customPieceOrder(cell, 'image') };`"
									>
										<img
											class="kdna-editor__cell-image-thumb"
											:src="cell.custom.image.url"
											:alt="cell.custom.image.alt || ''"
										/>
										<button
											type="button"
											class="kdna-editor__icon-button kdna-editor__icon-button--danger kdna-editor__piece-remove"
											@click="removeCustomImage(rowIdx, colIdx)"
											:aria-label="`<?php echo esc_js( __( 'Remove image', 'kdna-tables' ) ); ?>`"
										>&times;</button>
									</span>
								</div>

								<div class="kdna-editor__cell-toolbar kdna-editor__cell-toolbar--always">
									<button
										type="button"
										class="kdna-editor__icon-button"
										:class="customHasPiece(cell, 'icon') ? 'is-active' : ''"
										@click="openCustomIconPicker(rowIdx, colIdx)"
										:aria-label="`<?php echo esc_js( __( 'Add or change icon', 'kdna-tables' ) ); ?>`"
										:title="customHasPiece(cell, 'icon') ? `<?php echo esc_js( __( 'Change icon', 'kdna-tables' ) ); ?>` : `<?php echo esc_js( __( 'Add icon', 'kdna-tables' ) ); ?>`"
									><span aria-hidden="true">&#9734;</span></button>
									<button
										type="button"
										class="kdna-editor__icon-button"
										:class="customHasPiece(cell, 'image') ? 'is-active' : ''"
										@click="openCustomImagePicker(rowIdx, colIdx)"
										:aria-label="`<?php echo esc_js( __( 'Add or change image', 'kdna-tables' ) ); ?>`"
										:title="customHasPiece(cell, 'image') ? `<?php echo esc_js( __( 'Change image', 'kdna-tables' ) ); ?>` : `<?php echo esc_js( __( 'Add image', 'kdna-tables' ) ); ?>`"
									><span aria-hidden="true">&#9636;</span></button>
									<select
										class="kdna-editor__arrangement-select"
										x-show="cell.custom.content_types.length > 1"
										:value="cell.custom.arrangement"
										@change="setCustomArrangement(rowIdx, colIdx, $event.target.value)"
										:aria-label="`<?php echo esc_js( __( 'Arrangement', 'kdna-tables' ) ); ?>`"
									>
										<template x-for="opt in customArrangementOptions(cell)" :key="opt">
											<option :value="opt" x-text="formatArrangement(opt)"></option>
										</template>
									</select>
								</div>
							</div>
						</div>
					</template>
				</div>
			</article>
		</template>
	</section>

	<!-- Icon picker modal (shared across cell + item-image, but item-image
	     uses wp.media not this picker. This modal is for custom-cell icons only.) -->
	<div
		class="kdna-icon-picker__overlay"
		x-show="iconPicker.open"
		x-transition.opacity
		@click.self="closeIconPicker()"
		@keydown.escape.window="closeIconPicker()"
		role="dialog"
		aria-modal="true"
		aria-labelledby="kdna-icon-picker-title-cmp"
	>
		<div class="kdna-icon-picker">
			<header class="kdna-icon-picker__header">
				<h2 id="kdna-icon-picker-title-cmp" class="kdna-icon-picker__title">
					<?php esc_html_e( 'Pick an icon', 'kdna-tables' ); ?>
				</h2>
				<button
					type="button"
					class="kdna-editor__icon-button"
					@click="closeIconPicker()"
					:aria-label="`<?php echo esc_js( __( 'Close icon picker', 'kdna-tables' ) ); ?>`"
				>&times;</button>
			</header>
			<div class="kdna-icon-picker__controls">
				<input
					type="search"
					class="kdna-icon-picker__search"
					x-model.debounce.150ms="iconPicker.query"
					x-ref="iconSearchInput"
					placeholder="<?php esc_attr_e( 'Search icons by name or keyword', 'kdna-tables' ); ?>"
				/>
				<select x-model="iconPicker.library" class="kdna-icon-picker__library">
					<option value=""><?php esc_html_e( 'All libraries', 'kdna-tables' ); ?></option>
					<template x-for="lib in iconLibraries" :key="lib.key">
						<option :value="lib.key" x-text="lib.label"></option>
					</template>
				</select>
			</div>
			<div class="kdna-icon-picker__grid" x-show="filteredIcons.length">
				<template x-for="icon in filteredIcons" :key="icon.class">
					<button
						type="button"
						class="kdna-icon-picker__item"
						@click="selectIcon(icon)"
						:aria-label="icon.name"
						:title="icon.class"
					>
						<i :class="icon.class" aria-hidden="true"></i>
						<span class="kdna-icon-picker__item-label" x-text="icon.name"></span>
					</button>
				</template>
			</div>
			<p class="kdna-icon-picker__empty" x-show="!filteredIcons.length">
				<?php esc_html_e( 'No icons match. Try a different keyword.', 'kdna-tables' ); ?>
			</p>
		</div>
	</div>
</div>
