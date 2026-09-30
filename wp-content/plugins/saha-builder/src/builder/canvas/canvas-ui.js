/**
 * CSS giao diện soạn thảo chèn vào iframe (không ảnh hưởng frontend thật).
 */
export const CANVAS_UI_CSS = `
.saha-builder-canvas a { cursor: default; }
.saha-builder-canvas [data-saha-id].saha-is-hover { outline: 1px dashed rgba(56, 88, 233, .7); outline-offset: -1px; }
.saha-builder-canvas [data-saha-id].saha-is-selected { outline: 2px solid #3858e9 !important; outline-offset: -2px; }
.saha-builder-canvas .saha-section__inner:empty,
.saha-builder-canvas .saha-row:empty,
.saha-builder-canvas .saha-column:empty {
	min-height: 72px;
	background: repeating-linear-gradient(45deg, rgba(0, 0, 0, .03) 0 10px, rgba(0, 0, 0, .06) 10px 20px);
}
.saha-canvas-empty, .saha-canvas-pending {
	margin: 32px auto; max-width: 640px; padding: 40px 24px;
	border: 2px dashed #c3c4c7; border-radius: 8px;
	color: #50575e; font: 14px/1.5 system-ui, sans-serif; text-align: center;
}
.saha-canvas-pending { border-style: dotted; opacity: .7; }
.saha-canvas-toolbar {
	position: absolute; z-index: 2147483000; display: flex; align-items: center; gap: 1px;
	padding: 2px; border-radius: 4px 4px 0 0; background: #3858e9; color: #fff;
	font: 12px/1 system-ui, sans-serif; box-shadow: 0 1px 3px rgba(0, 0, 0, .2);
}
.saha-canvas-toolbar[hidden] { display: none; }
.saha-canvas-toolbar span { padding: 4px 6px; white-space: nowrap; }
.saha-canvas-toolbar button {
	all: unset; box-sizing: border-box; min-width: 24px; padding: 4px 6px;
	border-radius: 2px; text-align: center; cursor: pointer;
}
.saha-canvas-toolbar button:hover, .saha-canvas-toolbar button:focus-visible { background: rgba(255, 255, 255, .2); }
.saha-canvas-toolbar button[data-saha-action="drag"] { cursor: grab; }
.saha-canvas-drop { position: absolute; z-index: 2147483001; border-radius: 2px; background: #3858e9; pointer-events: none; }
.saha-canvas-drop.is-box { background: rgba(56, 88, 233, .12); outline: 2px dashed #3858e9; outline-offset: -2px; }
.saha-canvas-drop[hidden] { display: none; }
`;
