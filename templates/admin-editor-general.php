<?php
/**
 * Alpine.js matrix editor for general tables. Reads its seed from
 * window.kdnaTablesInitialState (emitted by KDNA_Tables_Editor) and
 * serialises edits into a single hidden input on the post form.
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
			</label>
			<label class="kdna-editor__flag">
				<input type="checkbox" x-model="state.general.first_column_is_header" />
				<?php esc_html_e( 'First column is header', 'kdna-tables' ); ?>
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

						<span class="kdna-editor__width" :title="`<?php echo esc_js( __( 'Width in %, 0 = auto', 'kdna-tables' ) ); ?>`">
							<input
								class="kdna-editor__width-input"
								type="number"
								min="0"
								max="100"
								step="1"
								:value="col.width"
								@input="setColumnWidth(colIdx, $event.target.value)"
								:aria-label="`<?php echo esc_js( __( 'Column width percent', 'kdna-tables' ) ); ?>`"
							/>
							<span>%</span>
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
</div>
