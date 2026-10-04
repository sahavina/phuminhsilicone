/**
 * CSS giao diện soạn thảo chèn vào iframe (không ảnh hưởng frontend thật).
 */
export const CANVAS_UI_CSS = `
.saha-builder-canvas a { cursor: default; }
.saha-builder-canvas [data-saha-id].saha-is-hover { outline: 1px dashed rgba(56, 88, 233, .7); outline-offset: -1px; }
.saha-builder-canvas [data-saha-id].saha-is-selected { outline: 2px solid #3858e9 !important; outline-offset: -2px; }
.saha-builder-canvas .saha-is-off { position: relative; opacity: .4; filter: grayscale(1); }
.saha-builder-canvas .saha-is-off::before { content: "Đã tắt — không hiển thị trên website"; position: absolute; z-index: 5; top: 6px; left: 6px; padding: 2px 8px; border-radius: 3px; background: #d63638; color: #fff; font: 600 11px/1.6 system-ui, sans-serif; pointer-events: none; }
.saha-canvas-toolbar [data-saha-action="toggle-hidden"].is-off { color: #ffb4b4; }
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
.saha-canvas-resize {
	position: absolute; z-index: 2147483000; box-sizing: border-box;
	border: 2px solid #fff; border-radius: 4px; background: #3858e9;
	box-shadow: 0 0 0 1px #3858e9, 0 1px 4px rgba(0, 0, 0, .25); touch-action: none;
}
.saha-canvas-resize--x { width: 10px; height: 32px; margin: -16px 0 0 -5px; cursor: ew-resize; }
.saha-canvas-resize--y { width: 32px; height: 10px; margin: -5px 0 0 -16px; cursor: ns-resize; }
.saha-canvas-resize[hidden] { display: none; }
.saha-canvas-size {
	position: absolute; z-index: 2147483001; padding: 2px 6px; border-radius: 3px;
	background: #1e1e1e; color: #fff; font: 11px/1.4 system-ui, sans-serif; pointer-events: none;
}
.saha-canvas-size[hidden] { display: none; }
`;
