/**
 * assets/js/builder.js
 *
 * Vanilla JS engine for the Admin Coupon Builder.
 * Handles dragging, resizing (via interact.js), adding elements to canvas,
 * and updating their properties.
 */

document.addEventListener('DOMContentLoaded', () => {

    // Core Elements
    const canvasEmptyState = document.getElementById('canvasEmptyState');
    const canvasWrapper = document.getElementById('canvasWrapper');
    const bgFileInput = document.getElementById('bgFileInput');
    const canvasBgImage = document.getElementById('canvasBgImage');
    const elementsContainer = document.getElementById('elementsContainer');
    const couponCanvas = document.getElementById('couponCanvas');

    // UI Buttons (Add Elements)
    const addQrBtn = document.getElementById('addQrBtn');
    const addTextTitleBtn = document.getElementById('addTextTitleBtn');
    const addTextBodyBtn = document.getElementById('addTextBodyBtn');
    const addBadgeDiscountBtn = document.getElementById('addBadgeDiscountBtn');

    // Properties Panel Elements
    const noSelectionState = document.getElementById('noSelectionState');
    const activeSelectionState = document.getElementById('activeSelectionState');
    const propElementType = document.getElementById('propElementType');
    const propWidth = document.getElementById('propWidth');
    const propHeight = document.getElementById('propHeight');
    const textPropertiesGroup = document.getElementById('textPropertiesGroup');
    const propTextContent = document.getElementById('propTextContent');
    const propTextSize = document.getElementById('propTextSize');
    const propTextColor = document.getElementById('propTextColor');
    const deleteElementBtn = document.getElementById('deleteElementBtn');
    const alignBtns = document.querySelectorAll('.align-btn');

    // Toolbar Elements
    const themeToggleBtn = document.getElementById('themeToggleBtn');
    const previewBtn = document.getElementById('previewBtn');
    const safeMarginOverlay = document.getElementById('safeMarginOverlay');
    const saveTemplateBtn = document.getElementById('saveTemplateBtn');
    const exportPdfBtn = document.getElementById('exportPdfBtn');

    // State Variables
    let selectedElement = null;
    let elementCounter = 0;
    let currentZoom = 1;

    // ----------------------------------------------------------------------
    // 1. Initialization & Theming
    // ----------------------------------------------------------------------

    // Simple dark mode toggle
    themeToggleBtn.addEventListener('click', () => {
        const html = document.documentElement;
        const isDark = html.getAttribute('data-theme') === 'dark';
        html.setAttribute('data-theme', isDark ? 'light' : 'dark');
        themeToggleBtn.innerHTML = isDark ? '<i class="ri-moon-line"></i>' : '<i class="ri-sun-line"></i>';
    });

    // ----------------------------------------------------------------------
    // 2. Base Canvas Setup (Background Upload)
    // ----------------------------------------------------------------------

    bgFileInput.addEventListener('change', (e) => {
        const file = e.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = (event) => {
                // Remove empty state, show canvas
                canvasEmptyState.classList.add('d-none');
                canvasWrapper.classList.remove('d-none');

                // Set Background Image
                canvasBgImage.src = event.target.result;

                toastr.success('Η εικόνα φόντου φορτώθηκε επιτυχώς.');
            };
            reader.readAsDataURL(file);
        }
    });

    // ----------------------------------------------------------------------
    // 3. Adding Elements to Canvas
    // ----------------------------------------------------------------------

    function createElementNode(type, defaultContent = '') {
        elementCounter++;
        const el = document.createElement('div');
        el.className = `builder-element element-${type}`;
        el.id = `el_${elementCounter}`;
        el.dataset.type = type;

        // Initial positioning and sizing attributes for interact.js
        el.dataset.x = 50;
        el.dataset.y = 50;

        // Default Styles
        el.style.transform = `translate(50px, 50px)`;

        if (type === 'qr') {
            el.style.width = '200px';
            el.style.height = '200px';
            el.innerHTML = `<span class="fw-bold bg-white px-2 py-1 border rounded shadow-sm text-dark" style="pointer-events: none;">QR CODE</span>`;
        } else if (type === 'text') {
            el.style.width = '300px';
            el.style.minHeight = '50px';
            el.style.fontSize = defaultContent === 'title' ? '48px' : '24px';
            el.style.fontWeight = defaultContent === 'title' ? 'bold' : 'normal';
            el.style.color = '#000000';
            el.style.textAlign = 'center';
            el.innerText = defaultContent === 'title' ? 'ΤΙΤΛΟΣ ΠΡΟΣΦΟΡΑΣ' : 'Περιγραφή ή όροι προσφοράς';
        } else if (type === 'badge') {
            el.style.width = '150px';
            el.style.height = '60px';
            el.style.fontSize = '24px';
            el.innerText = '-20%';
        }

        // Add Resize Handles (TL, TR, BL, BR)
        const handles = ['tl', 'tr', 'bl', 'br'];
        handles.forEach(h => {
            const handle = document.createElement('div');
            handle.className = `resize-handle resize-${h}`;
            el.appendChild(handle);
        });

        // Add Click Listener to Select Element
        el.addEventListener('mousedown', (e) => {
            e.stopPropagation(); // Prevent canvas background click from deselecting
            selectElement(el);
        });

        elementsContainer.appendChild(el);
        selectElement(el); // Auto-select new element
    }

    addQrBtn.addEventListener('click', () => createElementNode('qr'));
    addTextTitleBtn.addEventListener('click', () => createElementNode('text', 'title'));
    addTextBodyBtn.addEventListener('click', () => createElementNode('text', 'body'));
    addBadgeDiscountBtn.addEventListener('click', () => createElementNode('badge'));

    // Deselect if clicking on empty canvas area
    couponCanvas.addEventListener('mousedown', (e) => {
        if (e.target === elementsContainer || e.target === canvasBgImage) {
            deselectAll();
        }
    });

    // ----------------------------------------------------------------------
    // 4. Element Selection & Properties Panel Logic
    // ----------------------------------------------------------------------

    function deselectAll() {
        if (selectedElement) {
            selectedElement.classList.remove('is-selected');
            selectedElement = null;
        }
        noSelectionState.classList.remove('d-none');
        activeSelectionState.classList.add('d-none');
    }

    function selectElement(el) {
        deselectAll();
        selectedElement = el;
        el.classList.add('is-selected');

        // Update UI Panel
        noSelectionState.classList.add('d-none');
        activeSelectionState.classList.remove('d-none');

        const type = el.dataset.type;

        // Update generic dimensions
        propWidth.value = parseFloat(el.style.width) || el.offsetWidth;
        propHeight.value = parseFloat(el.style.height) || el.offsetHeight;

        // Toggle Text Properties visibility
        if (type === 'text' || type === 'badge') {
            propElementType.innerText = type === 'text' ? 'Κείμενο' : 'Σήμα/Badge';
            textPropertiesGroup.classList.remove('d-none');

            // Extract pure text without the resize handles HTML
            let clone = el.cloneNode(true);
            clone.querySelectorAll('.resize-handle').forEach(h => h.remove());
            propTextContent.value = clone.innerText;

            propTextSize.value = parseFloat(el.style.fontSize) || 24;
            // RGB to Hex for color picker
            const rgb = el.style.color || 'rgb(0,0,0)';
            propTextColor.value = rgbToHex(rgb);

            // Update Align buttons state
            alignBtns.forEach(btn => {
                if(btn.dataset.align === el.style.textAlign) {
                    btn.classList.add('active');
                } else {
                    btn.classList.remove('active');
                }
            });

        } else if (type === 'qr') {
            propElementType.innerText = 'QR Code';
            textPropertiesGroup.classList.add('d-none');
        }

        // Switch Sidebar Tab to Properties
        const triggerEl = document.querySelector('#properties-tab');
        bootstrap.Tab.getInstance(triggerEl)?.show() || new bootstrap.Tab(triggerEl).show();
    }

    // Update Element based on Property Input Changes
    propWidth.addEventListener('input', (e) => { if (selectedElement) selectedElement.style.width = `${e.target.value}px`; });
    propHeight.addEventListener('input', (e) => { if (selectedElement) selectedElement.style.height = `${e.target.value}px`; });

    propTextContent.addEventListener('input', (e) => {
        if (selectedElement && (selectedElement.dataset.type === 'text' || selectedElement.dataset.type === 'badge')) {
            // Update text while keeping resize handles
            updateElementText(selectedElement, e.target.value);
        }
    });

    propTextSize.addEventListener('input', (e) => { if (selectedElement) selectedElement.style.fontSize = `${e.target.value}px`; });
    propTextColor.addEventListener('input', (e) => { if (selectedElement) selectedElement.style.color = e.target.value; });

    alignBtns.forEach(btn => {
        btn.addEventListener('click', (e) => {
            if (selectedElement) {
                selectedElement.style.textAlign = btn.dataset.align;
                alignBtns.forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
            }
        });
    });

    deleteElementBtn.addEventListener('click', () => {
        if (selectedElement) {
            selectedElement.remove();
            deselectAll();
            toastr.info('Το στοιχείο διαγράφηκε.');
        }
    });

    // Helper: Safely update text without losing handles
    function updateElementText(el, newText) {
        // Find existing handles
        const handles = el.querySelectorAll('.resize-handle');
        // Clear inner HTML
        el.innerHTML = '';
        // Add new text node
        el.appendChild(document.createTextNode(newText));
        // Append handles back
        handles.forEach(h => el.appendChild(h));
    }

    // Helper: Convert rgb(0,0,0) to #000000
    function rgbToHex(color) {
        if (color.startsWith('#')) return color;
        const rgb = color.match(/^rgb\((\d+),\s*(\d+),\s*(\d+)\)$/);
        if (!rgb) return '#000000';
        function hex(x) { return ("0" + parseInt(x).toString(16)).slice(-2); }
        return "#" + hex(rgb[1]) + hex(rgb[2]) + hex(rgb[3]);
    }

    // ----------------------------------------------------------------------
    // 5. Drag & Drop and Resizing Engine (Interact.js)
    // ----------------------------------------------------------------------

    interact('.builder-element')
        .draggable({
            listeners: {
                start(event) { selectElement(event.target); },
                move(event) {
                    const target = event.target;
                    // keep the dragged position in the data-x/data-y attributes
                    const x = (parseFloat(target.dataset.x) || 0) + event.dx;
                    const y = (parseFloat(target.dataset.y) || 0) + event.dy;

                    // translate the element
                    target.style.transform = `translate(${x}px, ${y}px)`;

                    // update the posiion attributes
                    target.dataset.x = x;
                    target.dataset.y = y;
                },
            },
            modifiers: [
                interact.modifiers.restrictRect({
                    restriction: 'parent',
                    endOnly: false
                })
            ]
        })
        .resizable({
            // resize from all edges and corners
            edges: { left: '.resize-tl, .resize-bl', right: '.resize-tr, .resize-br', bottom: '.resize-bl, .resize-br', top: '.resize-tl, .resize-tr' },

            listeners: {
                start(event) { selectElement(event.target); },
                move: function (event) {
                    let { x, y } = event.target.dataset;

                    x = (parseFloat(x) || 0) + event.deltaRect.left;
                    y = (parseFloat(y) || 0) + event.deltaRect.top;

                    Object.assign(event.target.style, {
                        width: `${event.rect.width}px`,
                        height: `${event.rect.height}px`,
                        transform: `translate(${x}px, ${y}px)`
                    });

                    Object.assign(event.target.dataset, { x, y });

                    // Update UI inputs live
                    if(selectedElement === event.target) {
                        propWidth.value = Math.round(event.rect.width);
                        propHeight.value = Math.round(event.rect.height);
                    }
                }
            },
            modifiers: [
                // keep the edges inside the parent
                interact.modifiers.restrictEdges({ outer: 'parent' }),
                // minimum size
                interact.modifiers.restrictSize({
                    min: { width: 50, height: 50 }
                })
            ],
            inertia: true
        });

    // ----------------------------------------------------------------------
    // 6. Canvas Controls (Zoom, Preview, Actions)
    // ----------------------------------------------------------------------

    // Zoom logic
    const zoomInBtn = document.getElementById('zoomInBtn');
    const zoomOutBtn = document.getElementById('zoomOutBtn');
    const zoomLevelIndicator = document.getElementById('zoomLevelIndicator');

    function setZoom(scale) {
        currentZoom = Math.max(0.25, Math.min(scale, 2)); // Between 25% and 200%
        couponCanvas.style.transform = `scale(${currentZoom})`;
        couponCanvas.style.transformOrigin = 'top left';
        zoomLevelIndicator.innerText = `${Math.round(currentZoom * 100)}%`;
    }

    zoomInBtn.addEventListener('click', () => setZoom(currentZoom + 0.1));
    zoomOutBtn.addEventListener('click', () => setZoom(currentZoom - 0.1));

    // Preview Mode (Toggles Safe Margin Overlay and removes selections)
    let isPreviewMode = false;
    previewBtn.addEventListener('click', () => {
        isPreviewMode = !isPreviewMode;
        if (isPreviewMode) {
            deselectAll();
            safeMarginOverlay.classList.remove('d-none');
            previewBtn.classList.replace('btn-outline-secondary', 'btn-secondary');
            previewBtn.innerHTML = '<i class="ri-eye-off-line me-1"></i> Απόκρυψη Ορίων';
        } else {
            safeMarginOverlay.classList.add('d-none');
            previewBtn.classList.replace('btn-secondary', 'btn-outline-secondary');
            previewBtn.innerHTML = '<i class="ri-eye-line me-1"></i> Προεπισκόπηση';
        }
    });

    // Save Action Placeholder
    saveTemplateBtn.addEventListener('click', () => {
        deselectAll(); // Clean up selection handles before saving state
        const spinner = saveTemplateBtn.querySelector('.spinner-border');
        spinner.classList.remove('d-none');

        // Simulating Backend AJAX call
        setTimeout(() => {
            spinner.classList.add('d-none');

            // Extract DOM representing the template state
            // In a real scenario, we would parse elements to JSON coordinates and sizes
            const elementsState = Array.from(elementsContainer.children).map(el => ({
                id: el.id,
                type: el.dataset.type,
                width: el.style.width,
                height: el.style.height,
                x: el.dataset.x,
                y: el.dataset.y,
                text: el.innerText.trim(),
                color: el.style.color,
                fontSize: el.style.fontSize,
                textAlign: el.style.textAlign
            }));

            console.log("Template State to Save:", elementsState);

            Swal.fire({
                title: 'Επιτυχία!',
                text: 'Το πρότυπο κουπονιού αποθηκεύτηκε επιτυχώς στη βάση δεδομένων.',
                icon: 'success',
                confirmButtonText: 'ΟΚ'
            });
        }, 1000);
    });

    // Export PDF Placeholder
    exportPdfBtn.addEventListener('click', () => {
        deselectAll();
        Swal.fire({
            title: 'Εξαγωγή',
            text: 'Εκκίνηση διαδικασίας render (html2canvas -> jsPDF).',
            icon: 'info',
            confirmButtonText: 'Εντάξει'
        });
    });

});