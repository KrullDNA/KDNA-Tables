<?php
/**
 * Alpine.js matrix editor for general tables. Reads its seed from
 * window.kdnaTablesInitialState (emitted by KDNA_Tables_Editor) and
 * serialises edits into a single hidden input on the post form.
 *
 * Session 4 adds rich cell content: per-cell icon picker, image picker
 * (wp.media), and arrangement controls. Column widths get a unit
 * selector (% or px).
 *
 * @package KDNA_Tables
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div
	class="kdna-editor kdna-editor--general"
	x-data="kdnaTablesGeneralEditor()"
	x-init="init()"
	x-cloak
>
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
		<div class="kdna-editor__flags">
			<label class="kdna-editor__flag">
				<input type="checkbox" x-model="state.general.first_row_is_header" />
				<?php esc_html_e( 'First row is header', 'kdna-tables' ); ?>
				<span
					class="kdna-editor__help"
					tabindex="0"
					aria-label="<?php esc_attr_e( 'First row is header help', 'kdna-tables' ); ?>"
					title="<?php esc_attr_e( 'When on, the top row renders as a thead with th cells, given semantic table-header styling and read by screen readers as the header.', 'kdna-tables' ); ?>"
				>?</span>
			</label>
			<label class="kdna-editor__flag">
				<input type="checkbox" x-model="state.general.first_column_is_header" />
				<?php esc_html_e( 'First column is header', 'kdna-tables' ); ?>
				<span
					class="kdna-editor__help"
					tabindex="0"
					aria-label="<?php esc_attr_e( 'First column is header help', 'kdna-tables' ); ?>"
					title="<?php esc_attr_e( 'When on, the leftmost cell in every body row renders as th scope=row. Useful for row-label tables like specs or pricing.', 'kdna-tables' ); ?>"
				>?</span>
			</label>
		</div>
	</div>

	<div class="kdna-editor__matrix-wrap">
		<div
			class="kdna-editor__matrix"
			:style="`--kdna-cols: ${ state.general.columns.length };`"
			role="grid"
		>
			<!-- Header row: empty corner + column heads + add-column slot -->
			<div class="kdna-editor__corner" role="rowheader" aria-hidden="true"></div>
			<template x-for="(col, colIdx) in state.general.columns" :key="col.id">
				<div class="kdna-editor__col-head" role="columnheader">
					<input
						class="kdna-editor__col-label"
						type="text"
						x-model="col.label"
						:placeholder="`<?php echo esc_js( __( 'Column', 'kdna-tables' ) ); ?> ${ colIdx + 1 }`"
						:aria-label="`<?php echo esc_js( __( 'Column label', 'kdna-tables' ) ); ?> ${ colIdx + 1 }`"
					/>
					<div class="kdna-editor__col-toolbar" role="toolbar">
						<button
							type="button"
							class="kdna-editor__icon-button"
							:aria-pressed="col.alignment === 'left'"
							@click="setColumnAlignment(colIdx, 'left')"
							:aria-label="`<?php echo esc_js( __( 'Align column left', 'kdna-tables' ) ); ?>`"
						>L</button>
						<button
							type="button"
							class="kdna-editor__icon-button"
							:aria-pressed="col.alignment === 'centre'"
							@click="setColumnAlignment(colIdx, 'centre')"
							:aria-label="`<?php echo esc_js( __( 'Align column centre', 'kdna-tables' ) ); ?>`"
						>C</button>
						<button
							type="button"
							class="kdna-editor__icon-button"
							:aria-pressed="col.alignment === 'right'"
							@click="setColumnAlignment(colIdx, 'right')"
							:aria-label="`<?php echo esc_js( __( 'Align column right', 'kdna-tables' ) ); ?>`"
						>R</button>

						<span
							class="kdna-editor__width"
							:title="`<?php echo esc_js( __( 'Column width, 0 = auto', 'kdna-tables' ) ); ?>`"
						>
							<input
								class="kdna-editor__width-input"
								type="number"
								min="0"
								step="1"
								:value="col.width"
								@input="setColumnWidth(colIdx, $event.target.value)"
								:aria-label="`<?php echo esc_js( __( 'Column width number', 'kdna-tables' ) ); ?>`"
							/>
							<select
								class="kdna-editor__width-unit"
								:value="col.width_unit"
								@change="setColumnWidthUnit(colIdx, $event.target.value)"
								:aria-label="`<?php echo esc_js( __( 'Column width unit', 'kdna-tables' ) ); ?>`"
							>
								<option value="%">%</option>
								<option value="px">px</option>
							</select>
						</span>

						<button
							type="button"
							class="kdna-editor__icon-button"
							:disabled="colIdx === 0"
							@click="moveColumn(colIdx, -1)"
							:aria-label="`<?php echo esc_js( __( 'Move column left', 'kdna-tables' ) ); ?>`"
						>&larr;</button>
						<button
							type="button"
							class="kdna-editor__icon-button"
							:disabled="colIdx === state.general.columns.length - 1"
							@click="moveColumn(colIdx, 1)"
							:aria-label="`<?php echo esc_js( __( 'Move column right', 'kdna-tables' ) ); ?>`"
						>&rarr;</button>
						<button
							type="button"
							class="kdna-editor__icon-button kdna-editor__icon-button--danger"
							:disabled="state.general.columns.length <= 1"
							@click="removeColumn(colIdx)"
							:aria-label="`<?php echo esc_js( __( 'Delete column', 'kdna-tables' ) ); ?>`"
						>&times;</button>
					</div>
				</div>
			</template>
			<div class="kdna-editor__add-col">
				<button
					type="button"
					class="button button-secondary"
					:disabled="state.general.columns.length >= maxColumns"
					@click="addColumn()"
					:title="state.general.columns.length >= maxColumns ? `<?php echo esc_js( __( 'Maximum 10 columns', 'kdna-tables' ) ); ?>` : ''"
				><?php esc_html_e( '+ Column', 'kdna-tables' ); ?></button>
			</div>

			<!-- Body rows -->
			<template x-for="(row, rowIdx) in state.general.rows" :key="row.id">
				<div class="kdna-editor__row">
					<div class="kdna-editor__row-head" role="rowheader">
						<span class="kdna-editor__row-label" x-text="`<?php echo esc_js( __( 'Row', 'kdna-tables' ) ); ?> ${ rowIdx + 1 }`"></span>
						<div class="kdna-editor__row-toolbar" role="toolbar">
							<button
								type="button"
								class="kdna-editor__icon-button"
								:disabled="rowIdx === 0"
								@click="moveRow(rowIdx, -1)"
								:aria-label="`<?php echo esc_js( __( 'Move row up', 'kdna-tables' ) ); ?>`"
							>&uarr;</button>
							<button
								type="button"
								class="kdna-editor__icon-button"
								:disabled="rowIdx === state.general.rows.length - 1"
								@click="moveRow(rowIdx, 1)"
								:aria-label="`<?php echo esc_js( __( 'Move row down', 'kdna-tables' ) ); ?>`"
							>&darr;</button>
							<button
								type="button"
								class="kdna-editor__icon-button kdna-editor__icon-button--danger"
								@click="removeRow(rowIdx)"
								:aria-label="`<?php echo esc_js( __( 'Delete row', 'kdna-tables' ) ); ?>`"
							>&times;</button>
						</div>
					</div>

					<template x-for="(cell, colIdx) in row.cells" :key="cell.id">
						<div
							class="kdna-editor__cell"
							:class="[
								cell.alignment === 'left' ? 'kdna-editor__cell--align-left' : '',
								cell.alignment === 'centre' ? 'kdna-editor__cell--align-centre' : '',
								cell.alignment === 'right' ? 'kdna-editor__cell--align-right' : '',
								isCellFocused(rowIdx, colIdx) ? 'is-focused' : ''
							]"
						>
							<!-- Cell preview, multi-piece. Pieces stay in DOM order;
							     visibility and order are controlled per piece so the
							     contenteditable does not unmount on arrangement
							     change (would lose cursor). -->
							<div class="kdna-editor__cell-preview">
								<!-- Icon piece -->
								<span
									class="kdna-editor__cell-piece kdna-editor__cell-piece--icon"
									x-show="hasPiece(cell, 'icon')"
									:style="`order: ${ pieceOrder(cell, 'icon') };`"
								>
									<i :class="iconClassesFor(cell)" aria-hidden="true"></i>
									<button
										type="button"
										class="kdna-editor__icon-button kdna-editor__icon-button--danger kdna-editor__piece-remove"
										@click="removeIcon(rowIdx, colIdx)"
										:aria-label="`<?php echo esc_js( __( 'Remove icon', 'kdna-tables' ) ); ?>`"
										title="<?php echo esc_attr__( 'Remove icon', 'kdna-tables' ); ?>"
									>&times;</button>
								</span>
								<!-- Text piece. Always present in DOM so contenteditable
								     keeps focus across content_types toggles. -->
								<div
									class="kdna-editor__cell-piece kdna-editor__cell-piece--text"
									:style="`order: ${ pieceOrder(cell, 'text') };`"
								>
									<div
										class="kdna-editor__cell-text"
										contenteditable="plaintext-only"
										role="textbox"
										spellcheck="true"
										x-init="$el.innerText = cell.text"
										@input="setCellText(rowIdx, colIdx, $event.target.innerText)"
										@focus="focusedCell = `${ rowIdx }-${ colIdx }`"
										@blur="onCellBlur($event)"
										@keydown.enter="if (!$event.shiftKey) { $event.preventDefault(); $event.target.blur(); }"
									></div>
								</div>
								<!-- Image piece -->
								<span
									class="kdna-editor__cell-piece kdna-editor__cell-piece--image"
									x-show="hasPiece(cell, 'image')"
									:style="`order: ${ pieceOrder(cell, 'image') };`"
								>
									<img
										class="kdna-editor__cell-image-thumb"
										:src="cell.image.url"
										:alt="cell.image.alt || ''"
									/>
									<button
										type="button"
										class="kdna-editor__icon-button kdna-editor__icon-button--danger kdna-editor__piece-remove"
										@click="removeImage(rowIdx, colIdx)"
										:aria-label="`<?php echo esc_js( __( 'Remove image', 'kdna-tables' ) ); ?>`"
										title="<?php echo esc_attr__( 'Remove image', 'kdna-tables' ); ?>"
									>&times;</button>
								</span>
							</div>

							<div class="kdna-editor__cell-toolbar" role="toolbar">
								<button
									type="button"
									class="kdna-editor__icon-button"
									:aria-pressed="cell.alignment === 'left'"
									@mousedown.prevent
									@click="setAlignment(rowIdx, colIdx, cell.alignment === 'left' ? '' : 'left')"
									:aria-label="`<?php echo esc_js( __( 'Override cell alignment to left', 'kdna-tables' ) ); ?>`"
								>L</button>
								<button
									type="button"
									class="kdna-editor__icon-button"
									:aria-pressed="cell.alignment === 'centre'"
									@mousedown.prevent
									@click="setAlignment(rowIdx, colIdx, cell.alignment === 'centre' ? '' : 'centre')"
									:aria-label="`<?php echo esc_js( __( 'Override cell alignment to centre', 'kdna-tables' ) ); ?>`"
								>C</button>
								<button
									type="button"
									class="kdna-editor__icon-button"
									:aria-pressed="cell.alignment === 'right'"
									@mousedown.prevent
									@click="setAlignment(rowIdx, colIdx, cell.alignment === 'right' ? '' : 'right')"
									:aria-label="`<?php echo esc_js( __( 'Override cell alignment to right', 'kdna-tables' ) ); ?>`"
								>R</button>

								<button
									type="button"
									class="kdna-editor__icon-button"
									:class="hasPiece(cell, 'icon') ? 'is-active' : ''"
									@mousedown.prevent
									@click="openIconPicker(rowIdx, colIdx)"
									:aria-label="`<?php echo esc_js( __( 'Add or change icon', 'kdna-tables' ) ); ?>`"
									:title="hasPiece(cell, 'icon') ? `<?php echo esc_js( __( 'Change icon', 'kdna-tables' ) ); ?>` : `<?php echo esc_js( __( 'Add icon', 'kdna-tables' ) ); ?>`"
								>
									<span aria-hidden="true">&#9734;</span>
								</button>
								<button
									type="button"
									class="kdna-editor__icon-button"
									:class="hasPiece(cell, 'image') ? 'is-active' : ''"
									@mousedown.prevent
									@click="openImagePicker(rowIdx, colIdx)"
									:aria-label="`<?php echo esc_js( __( 'Add or change image', 'kdna-tables' ) ); ?>`"
									:title="hasPiece(cell, 'image') ? `<?php echo esc_js( __( 'Change image', 'kdna-tables' ) ); ?>` : `<?php echo esc_js( __( 'Add image', 'kdna-tables' ) ); ?>`"
								>
									<span aria-hidden="true">&#9636;</span>
								</button>

								<select
									class="kdna-editor__arrangement-select"
									x-show="cell.content_types.length > 1"
									:value="cell.arrangement"
									@change="setArrangement(rowIdx, colIdx, $event.target.value)"
									:aria-label="`<?php echo esc_js( __( 'Arrangement', 'kdna-tables' ) ); ?>`"
									@mousedown.stop
								>
									<template x-for="opt in arrangementOptions(cell)" :key="opt">
										<option :value="opt" x-text="formatArrangement(opt)"></option>
									</template>
								</select>
							</div>
						</div>
					</template>
				</div>
			</template>

			<!-- Add-row footer occupying the full grid width -->
			<div class="kdna-editor__add-row" :style="`grid-column: 1 / span ${ state.general.columns.length + 2 };`">
				<button type="button" class="button button-secondary" @click="addRow()">
					<?php esc_html_e( '+ Row', 'kdna-tables' ); ?>
				</button>
			</div>
		</div>
	</div>

	<!-- Icon picker modal -->
	<div
		class="kdna-icon-picker__overlay"
		x-show="iconPicker.open"
		x-transition.opacity
		@click.self="closeIconPicker()"
		@keydown.escape.window="closeIconPicker()"
		role="dialog"
		aria-modal="true"
		aria-labelledby="kdna-icon-picker-title"
	>
		<div class="kdna-icon-picker">
			<header class="kdna-icon-picker__header">
				<h2 id="kdna-icon-picker-title" class="kdna-icon-picker__title">
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
