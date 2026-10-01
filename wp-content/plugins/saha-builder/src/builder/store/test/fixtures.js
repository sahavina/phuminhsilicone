/**
 * Định nghĩa element giống server (ElementRegistry::forClient) — chỉ phần quy tắc cha–con.
 */
export const defs = {
	section: {
		name: 'Section',
		allowedParents: [ 'root' ],
		allowedChildren: [ 'row', 'heading', 'text', 'button', 'image' ],
	},
	row: {
		name: 'Hàng',
		allowedParents: [ 'section', 'column' ],
		allowedChildren: [ 'column' ],
		initialChildren: [ 'column', 'column' ],
	},
	column: {
		name: 'Cột',
		allowedParents: [ 'row' ],
		allowedChildren: [ 'row', 'heading', 'text', 'button', 'image' ],
	},
	heading: {
		name: 'Tiêu đề',
		allowedParents: [ 'column', 'section' ],
		allowedChildren: [],
	},
	text: {
		name: 'Văn bản',
		allowedParents: [ 'column', 'section' ],
		allowedChildren: [],
	},
	button: {
		name: 'Nút',
		allowedParents: [ 'column', 'section' ],
		allowedChildren: [],
	},
	image: {
		name: 'Ảnh',
		allowedParents: [ 'column', 'section' ],
		allowedChildren: [],
	},
};

/**
 * Tài liệu mẫu: s1 > r1 > (c1 > h1, t1) + (c2); s2 > b1.
 *
 * @return {Object} Tài liệu.
 */
export function sampleDoc() {
	return {
		version: 1,
		elements: [
			{
				id: 's1',
				type: 'section',
				props: {},
				children: [
					{
						id: 'r1',
						type: 'row',
						props: {},
						children: [
							{
								id: 'c1',
								type: 'column',
								props: {},
								children: [
									{
										id: 'h1',
										type: 'heading',
										props: { text: 'Xin chào' },
									},
									{
										id: 't1',
										type: 'text',
										props: { content: '<p>Nội dung</p>' },
									},
								],
							},
							{
								id: 'c2',
								type: 'column',
								props: {},
								children: [],
							},
						],
					},
				],
			},
			{
				id: 's2',
				type: 'section',
				props: {},
				children: [ { id: 'b1', type: 'button', props: {} } ],
			},
		],
	};
}
