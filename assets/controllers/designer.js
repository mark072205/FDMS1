// Designer header dropdown functionality
function toggleDropdown() {
    const dropdown = document.getElementById('designerDropdown');
    if (dropdown) {
        dropdown.classList.toggle('show');
    }
}

// Close dropdown when clicking outside
document.addEventListener('click', function(event) {
    const dropdown = document.getElementById('designerDropdown');
    const badge = document.querySelector('.designer-badge');
    
    if (dropdown && badge && !badge.contains(event.target) && !dropdown.contains(event.target)) {
        dropdown.classList.remove('show');
    }
});

// Search functionality for designer search
document.addEventListener('DOMContentLoaded', function() {
    // Edit Photo Modal logic
    const openBtn = document.getElementById('openEditPhoto');
    const modal = document.getElementById('editPhotoModal');
    const closeBtn = document.getElementById('closeEditPhoto');
    const backdrop = modal ? modal.querySelector('.designer-photo-modal__backdrop') : null;
    const fileInput = document.getElementById('editPhotoFile');
    const changeBtn = document.getElementById('editPhotoChange');
    const saveBtn = document.getElementById('editPhotoSave');
    const controls = document.getElementById('editPhotoControls');
    const zoomInput = document.getElementById('editPhotoZoom');
    const zoomOutBtn = document.getElementById('editPhotoZoomOut');
    const zoomInBtn = document.getElementById('editPhotoZoomIn');
    const zoomValue = document.getElementById('editPhotoZoomValue');
    const rotateBtn = document.getElementById('editPhotoRotate');
    const previewImg = document.getElementById('editPhotoPreview');
    const thumbImgs = document.querySelectorAll('.designer-photo-thumb img');
    const cropperEl = document.querySelector('.designer-photo-cropper');
    const moveBadge = document.querySelector('.designer-photo-move-badge');

    let baseScale = 1; // fits main image to main cropper (cover)
    let thumbBaseScales = []; // per-thumb base scales (fit to each thumb)
    let scale = 1; // current main scale = baseScale * zoom factor
    let rotation = 0;
    const ZOOM_DIVISOR = 20; // 0..20 => ~1.0..2.0 (stronger zoom)

    // drag state
    let isDragging = false;
    let dragStartX = 0;
    let dragStartY = 0;
    let imgOffsetX = 0; // px relative to cropper center
    let imgOffsetY = 0; // px relative to cropper center
    let startOffsetX = 0;
    let startOffsetY = 0;

    function computeFitScale(containerWidth, containerHeight, imgW, imgH) {
        if (!containerWidth || !containerHeight || !imgW || !imgH) return 1;
        const fitScale = Math.max(containerWidth / imgW, containerHeight / imgH);
        return fitScale > 0 ? fitScale : 1;
    }

    function computeBaseScaleForPreview() {
        if (!previewImg) return 1;
        const cropper = previewImg.closest('.designer-photo-cropper');
        if (!cropper || !previewImg.naturalWidth || !previewImg.naturalHeight) return 1;
        
        // Check if this is an uploaded profile picture (already cropped)
        const isUploaded = isUploadedProfilePicture(previewImg.src);
        
        if (isUploaded) {
            // Uploaded images are already cropped to fit the circle
            // Scale them to fit the cropper size exactly (1:1 pixel ratio ideally)
            const cropperSize = cropper.clientWidth;
            const imgSize = Math.max(previewImg.naturalWidth, previewImg.naturalHeight);
            // If image matches cropper size, use 1:1 scale
            if (Math.abs(imgSize - cropperSize) < 10) {
                return 1;
            }
            // Otherwise scale to fit
            return cropperSize / imgSize;
        }
        
        // For new images, use cover mode (scale to cover the entire cropper)
        return computeFitScale(cropper.clientWidth, cropper.clientHeight, previewImg.naturalWidth, previewImg.naturalHeight);
    }

    function computeBaseScalesForThumbs() {
        thumbBaseScales = [];
        if (!thumbImgs || !thumbImgs.length || !previewImg) return;
        thumbImgs.forEach(function(img, idx) {
            const container = img.closest('.designer-photo-thumb');
            const scaleVal = container
                ? computeFitScale(container.clientWidth, container.clientHeight, previewImg.naturalWidth, previewImg.naturalHeight)
                : 1;
            thumbBaseScales[idx] = scaleVal;
        });
    }

    function hasPreviewImage() {
        if (!previewImg) return false;
        const attr = previewImg.getAttribute('src');
        return !!(attr && attr.trim() !== '');
    }
    function syncControlsVisibility() {
        const hasImage = hasPreviewImage();
        if (controls) controls.classList.toggle('hidden', !hasImage);
        if (changeBtn) changeBtn.classList.toggle('hidden', !hasImage);
    }
    // Function to save editor state to localStorage
    function saveEditorState() {
        if (!previewImg || !hasPreviewImage()) return;
        const imageSrc = previewImg.src;
        const state = {
            zoom: zoomInput ? parseInt(zoomInput.value || '0', 10) : 0,
            rotation: rotation,
            offsetX: imgOffsetX,
            offsetY: imgOffsetY,
            imageSrc: imageSrc // Use as key identifier
        };
        localStorage.setItem('profilePictureEditorState', JSON.stringify(state));
    }

    // Function to check if image is an uploaded profile picture (vs a new file being edited)
    function isUploadedProfilePicture(src) {
        // Uploaded profile pictures have paths like /uploads/profile_pictures/profile_...
        // New files being edited have data URLs (data:image/...)
        return src && !src.startsWith('data:') && (src.includes('/uploads/profile_pictures/') || src.includes('profile_'));
    }

    // Function to restore editor state from localStorage
    function restoreEditorState(imageSrc) {
        try {
            // Don't restore zoom for uploaded profile pictures - they're already processed
            // Only restore position/rotation for fine-tuning
            const isUploaded = isUploadedProfilePicture(imageSrc);
            
            const saved = localStorage.getItem('profilePictureEditorState');
            if (!saved) return false;
            
            const state = JSON.parse(saved);
            // Only restore if it's the same image
            if (state.imageSrc && state.imageSrc === imageSrc) {
                // For uploaded images, reset zoom to 0 since they're already cropped
                if (zoomInput) {
                    if (isUploaded) {
                        zoomInput.value = '0';
                    } else {
                        zoomInput.value = String(Math.max(0, Math.min(20, state.zoom || 0)));
                    }
                }
                
                // Still restore rotation and position for fine-tuning
                rotation = state.rotation || 0;
                imgOffsetX = state.offsetX || 0;
                imgOffsetY = state.offsetY || 0;
                return true;
            }
        } catch (e) {
            console.error('Failed to restore editor state:', e);
        }
        return false;
    }

    function openModal() { 
        if (modal) { 
            try {
                modal.classList.remove('hidden');
                modal.classList.remove('closing');
                syncControlsVisibility();
                
                // Restore state if editing existing image
                if (hasPreviewImage() && previewImg && previewImg.src) {
                    // Small delay to ensure everything is ready
                    setTimeout(function() {
                        try {
                            if (previewImg.naturalWidth && previewImg.naturalHeight) {
                                baseScale = computeBaseScaleForPreview();
                                computeBaseScalesForThumbs();
                                
                                // For uploaded images, reset zoom (they're already processed)
                                if (isUploadedProfilePicture(previewImg.src)) {
                                    rotation = 0;
                                    imgOffsetX = 0;
                                    imgOffsetY = 0;
                                    if (zoomInput) zoomInput.value = '0';
                                } else {
                                    // For new files, try to restore state
                                    restoreEditorState(previewImg.src);
                                }
                                
                                applyTransform();
                                if (zoomValue && zoomInput) {
                                    positionZoomBubble();
                                }
                            }
                        } catch (e) {
                            console.error('Error initializing modal image:', e);
                        }
                    }, 100);
                }
            } catch (e) {
                console.error('Error opening modal:', e);
            }
        } 
    }
    function closeModal() { 
        if (modal && !modal.classList.contains('closing')) {
            modal.classList.add('closing');
            setTimeout(function() {
                if (modal) {
                    modal.classList.add('hidden');
                    modal.classList.remove('closing');
                }
            }, 250); // Match the longest animation duration
        }
    }
    function positionZoomBubble() {
        if (!zoomInput || !zoomValue) return;
        const min = parseFloat(zoomInput.min || '0');
        const max = parseFloat(zoomInput.max || '100');
        const val = parseFloat(zoomInput.value || String(min));
        const pct = (val - min) / (max - min); // 0..1
        
        // Position relative to the slider wrapper
        const sliderWrapper = zoomInput.closest('.zoom-slider-wrapper');
        if (!sliderWrapper) return;
        
        // Use requestAnimationFrame to ensure layout is complete
        requestAnimationFrame(function() {
            const inputRect = zoomInput.getBoundingClientRect();
            const wrapperRect = sliderWrapper.getBoundingClientRect();
            
            // Thumb size is 18px (radius = 9px)
            // Range sliders position thumb center accounting for thumb width
            // At min (0%), thumb center is at thumbRadius (9px) from left
            // At max (100%), thumb center is at (trackWidth - thumbRadius) from left
            const thumbRadius = 9;
            const trackWidth = inputRect.width;
            const availableWidth = trackWidth - (thumbRadius * 2);
            
            // Calculate thumb center position
            const thumbCenterPosition = thumbRadius + (pct * availableWidth);
            
            // Position relative to the wrapper's left edge
            const leftPx = (inputRect.left - wrapperRect.left) + thumbCenterPosition;
            zoomValue.style.left = leftPx + 'px';
            zoomValue.style.transform = 'translateX(-50%)';
        });
    }

    function applyTransform() {
        if (!previewImg || !cropperEl) return; // Safety check
        const level = zoomInput ? parseInt(zoomInput.value || '0', 10) : 0;
        const zoomFactor = 1 + (isNaN(level) ? 0 : level) / ZOOM_DIVISOR;
        const mainScale = (baseScale || 1) * zoomFactor;
        // clamp offsets so image always covers the cropper and compute normalized offsets
        let normX = 0, normY = 0;
        // helper: compute axis-aligned bounding box of a rotated rectangle
        function rotatedBounds(width, height, angleDeg) {
            const theta = (Math.abs(angleDeg) % 360) * Math.PI / 180;
            const cos = Math.cos(theta);
            const sin = Math.sin(theta);
            const w = Math.abs(width * cos) + Math.abs(height * sin);
            const h = Math.abs(width * sin) + Math.abs(height * cos);
            return { w: w, h: h };
        }
        if (previewImg && cropperEl && previewImg.naturalWidth && previewImg.naturalHeight) {
            // account for rotation when computing displayed bounding dims
            const scaledW = previewImg.naturalWidth * mainScale;
            const scaledH = previewImg.naturalHeight * mainScale;
            const rb = rotatedBounds(scaledW, scaledH, rotation);
            const dispW = rb.w;
            const dispH = rb.h;
            
            const cropperW = cropperEl.clientWidth;
            const cropperH = cropperEl.clientHeight;
            const cropperRadius = cropperW / 2;
            
            // Simplified but more reliable bounds calculation for circular cropper
            // Use conservative bounds to ensure circle is always covered
            const minRequiredSize = cropperW * 1.414; // diagonal (more conservative than diameter)
            const safetyFactor = 0.75; // 25% safety margin
            
            let maxX = 0;
            let maxY = 0;
            
            if (dispW > minRequiredSize && dispH > minRequiredSize) {
                // Calculate excess size beyond minimum requirement
                const excessW = dispW - minRequiredSize;
                const excessH = dispH - minRequiredSize;
                
                // Allow movement, but very conservatively
                maxX = (excessW / 2) * safetyFactor;
                maxY = (excessH / 2) * safetyFactor;
            }
            
            // Clamp offsets strictly
            if (imgOffsetX > maxX) {
                imgOffsetX = maxX;
            } else if (imgOffsetX < -maxX) {
                imgOffsetX = -maxX;
            }
            if (imgOffsetY > maxY) {
                imgOffsetY = maxY;
            } else if (imgOffsetY < -maxY) {
                imgOffsetY = -maxY;
            }
            
            // Compute normalized offsets for thumb sync
            const simpleMaxX = Math.max(0, (dispW - cropperW) / 2);
            const simpleMaxY = Math.max(0, (dispH - cropperH) / 2);
            normX = simpleMaxX > 0 ? (imgOffsetX / simpleMaxX) : 0;
            normY = simpleMaxY > 0 ? (imgOffsetY / simpleMaxY) : 0;
        }
        if (previewImg) {
            previewImg.style.transform = `translate(calc(-50% + ${imgOffsetX}px), calc(-50% + ${imgOffsetY}px)) scale(${mainScale}) rotate(${rotation}deg)`;
        }
        if (thumbImgs && thumbImgs.length) {
            thumbImgs.forEach(function(img, idx) {
                const container = img.closest('.designer-photo-thumb');
                const containerW = container ? container.clientWidth : 0;
                const containerH = container ? container.clientHeight : 0;
                const thumbScale = (thumbBaseScales[idx] || 1) * zoomFactor;
                // compute per-thumb max offsets and map normalized offsets from main to thumb
                let offX = 0, offY = 0;
                if (previewImg && previewImg.naturalWidth && previewImg.naturalHeight && containerW && containerH) {
                    const scaledW = previewImg.naturalWidth * thumbScale;
                    const scaledH = previewImg.naturalHeight * thumbScale;
                    const rb = rotatedBounds(scaledW, scaledH, rotation);
                    const dispW = rb.w;
                    const dispH = rb.h;
                    const tMaxX = Math.max(0, (dispW - containerW) / 2);
                    const tMaxY = Math.max(0, (dispH - containerH) / 2);
                    offX = normX * tMaxX;
                    offY = normY * tMaxY;
                }
                img.style.transform = `translate(calc(-50% + ${offX}px), calc(-50% + ${offY}px)) scale(${thumbScale}) rotate(${rotation}deg)`;
            });
        }
        if (zoomValue && zoomInput) {
            if (!isNaN(level)) zoomValue.textContent = String(level);
            positionZoomBubble();
        }
    }

    if (openBtn && modal) openBtn.addEventListener('click', function(e) { e.preventDefault(); openModal(); });
    if (closeBtn) closeBtn.addEventListener('click', closeModal);
    if (backdrop) backdrop.addEventListener('click', closeModal);

    // show/hide Move badge based on hover
    if (cropperEl) {
        cropperEl.addEventListener('mouseenter', function() {
            // hide only when image is present
            if (hasPreviewImage() && moveBadge) moveBadge.classList.add('hidden');
        });
        cropperEl.addEventListener('mouseleave', function() {
            if (!isDragging && hasPreviewImage() && moveBadge) moveBadge.classList.remove('hidden');
        });
    }

    // dragging to move image
    function onMouseDown(e) {
        if (!cropperEl || !hasPreviewImage()) return; // drag only when image attached
        isDragging = true;
        cropperEl.classList.add('dragging');
        if (moveBadge) moveBadge.classList.add('hidden');
        dragStartX = e.clientX;
        dragStartY = e.clientY;
        startOffsetX = imgOffsetX;
        startOffsetY = imgOffsetY;
        window.addEventListener('mousemove', onMouseMove);
        window.addEventListener('mouseup', onMouseUp, { once: true });
        e.preventDefault();
    }
    function onMouseMove(e) {
        if (!isDragging) return;
        const dx = e.clientX - dragStartX;
        const dy = e.clientY - dragStartY;
        
        // Calculate provisional offsets
        let newOffsetX = startOffsetX + dx;
        let newOffsetY = startOffsetY + dy;
        
            // Calculate bounds before applying
        if (previewImg && cropperEl && previewImg.naturalWidth && previewImg.naturalHeight) {
            const dragLevel = zoomInput ? parseInt(zoomInput.value || '0', 10) : 0;
            const dragZoomFactor = 1 + (isNaN(dragLevel) ? 0 : dragLevel) / ZOOM_DIVISOR;
            const dragMainScale = (baseScale || 1) * dragZoomFactor;
            const dragScaledW = previewImg.naturalWidth * dragMainScale;
            const dragScaledH = previewImg.naturalHeight * dragMainScale;
            
            function dragRotatedBounds(width, height, angleDeg) {
                const theta = (Math.abs(angleDeg) % 360) * Math.PI / 180;
                const cos = Math.cos(theta);
                const sin = Math.sin(theta);
                const w = Math.abs(width * cos) + Math.abs(height * sin);
                const h = Math.abs(width * sin) + Math.abs(height * cos);
                return { w: w, h: h };
            }
            
            const dragRb = dragRotatedBounds(dragScaledW, dragScaledH, rotation);
            const dragDispW = dragRb.w;
            const dragDispH = dragRb.h;
            
            const dragCropperW = cropperEl.clientWidth;
            const dragCropperH = cropperEl.clientHeight;
            const dragMinRequiredSize = dragCropperW * 1.414; // diagonal
            const dragSafetyFactor = 0.75; // 25% safety margin
            
            // Simplified bounds calculation for dragging
            let dragMaxX = 0;
            let dragMaxY = 0;
            
            if (dragDispW > dragMinRequiredSize && dragDispH > dragMinRequiredSize) {
                const dragExcessW = dragDispW - dragMinRequiredSize;
                const dragExcessH = dragDispH - dragMinRequiredSize;
                dragMaxX = (dragExcessW / 2) * dragSafetyFactor;
                dragMaxY = (dragExcessH / 2) * dragSafetyFactor;
            }
            
            const maxX = dragMaxX;
            const maxY = dragMaxY;
            
            // Clamp the new offsets immediately
            if (newOffsetX > maxX) {
                newOffsetX = maxX;
            } else if (newOffsetX < -maxX) {
                newOffsetX = -maxX;
            }
            if (newOffsetY > maxY) {
                newOffsetY = maxY;
            } else if (newOffsetY < -maxY) {
                newOffsetY = -maxY;
            }
            
            // Update actual offsets
            imgOffsetX = newOffsetX;
            imgOffsetY = newOffsetY;
            
            // Update drag start if we hit a boundary to prevent drift
            if (Math.abs(newOffsetX) >= maxX || Math.abs(newOffsetY) >= maxY) {
                dragStartX = e.clientX;
                dragStartY = e.clientY;
                startOffsetX = imgOffsetX;
                startOffsetY = imgOffsetY;
            }
        } else {
            imgOffsetX = newOffsetX;
            imgOffsetY = newOffsetY;
        }
        
        // Apply transform with clamped offsets
        applyTransform();
        
        saveEditorState(); // Save state on move
    }
    function onMouseUp() {
        isDragging = false;
        if (cropperEl) cropperEl.classList.remove('dragging');
        if (moveBadge) moveBadge.classList.remove('hidden');
        window.removeEventListener('mousemove', onMouseMove);
    }
    if (cropperEl) {
        cropperEl.addEventListener('mousedown', onMouseDown);
    }

    // helper to load an image file into preview and thumbs
    function loadImageFile(file) {
        if (!file) return;
        const reader = new FileReader();
        reader.onload = function(e) {
            if (previewImg) {
                previewImg.src = e.target.result;
                previewImg.style.display = '';
                if (thumbImgs && thumbImgs.length) {
                    thumbImgs.forEach(function(img) { img.src = e.target.result; });
                }
                // compute base scale on image load so it starts unzoomed (fit & centered)
                previewImg.onload = function() {
                    baseScale = computeBaseScaleForPreview();
                    computeBaseScalesForThumbs();
                    
                    // Check if this is a new image or existing one
                    const isNewImage = !restoreEditorState(previewImg.src);
                    if (isNewImage) {
                        // Reset to defaults for new images
                        rotation = 0;
                        imgOffsetX = 0;
                        imgOffsetY = 0;
                        if (zoomInput) zoomInput.value = '0';
                    }
                    
                    applyTransform();
                    if (zoomValue && zoomInput) {
                        positionZoomBubble();
                    }
                };
                // remove empty-state visuals if present
                const cropper = previewImg.closest('.designer-photo-cropper');
                if (cropper) {
                    cropper.classList.remove('designer-photo-cropper--empty');
                    const hint = cropper.querySelector('.designer-photo-drop-hint');
                    if (hint) hint.remove();
                    // ensure Move badge exists and is visible (shown when cursor not inside)
                    if (!cropper.querySelector('.designer-photo-move-badge')) {
                        const badge = document.createElement('div');
                        badge.className = 'designer-photo-move-badge';
                        badge.innerHTML = '<i class="bi bi-arrows-move"></i>Move';
                        cropper.appendChild(badge);
                    }
                    const badgeEl = cropper.querySelector('.designer-photo-move-badge');
                    if (badgeEl) badgeEl.classList.remove('hidden');
                }
                // reveal controls
                if (controls) controls.classList.remove('hidden');
                if (changeBtn) changeBtn.classList.remove('hidden');
            }
        };
        reader.readAsDataURL(file);
    }

    if (changeBtn && fileInput) {
        changeBtn.addEventListener('click', function() { fileInput.click(); });
        fileInput.addEventListener('change', function() {
            const file = this.files && this.files[0];
            loadImageFile(file);
        });
    }

    // Allow clicking empty cropper to attach image
    if (cropperEl) {
        cropperEl.addEventListener('click', function() {
            if (!hasPreviewImage() && fileInput) fileInput.click();
        });
        // enable drag & drop to attach image
        ['dragenter', 'dragover'].forEach(function(evtName) {
            cropperEl.addEventListener(evtName, function(e) {
                e.preventDefault();
                e.stopPropagation();
                if (cropperEl.classList) cropperEl.classList.add('is-dragover');
            });
        });
        ['dragleave', 'dragend', 'drop'].forEach(function(evtName) {
            cropperEl.addEventListener(evtName, function(e) {
                if (e) { e.preventDefault(); e.stopPropagation(); }
                if (cropperEl.classList) cropperEl.classList.remove('is-dragover');
            });
        });
        cropperEl.addEventListener('drop', function(e) {
            const dt = e.dataTransfer;
            if (!dt || !dt.files || !dt.files.length) return;
            const file = dt.files[0];
            if (!file.type || !file.type.startsWith('image/')) return;
            loadImageFile(file);
        });
    }

    if (zoomInput) {
        zoomInput.addEventListener('input', function() {
            const level = Math.min(20, Math.max(0, parseInt(this.value || '0', 10)));
            this.value = String(level);
            // scale derived inside applyTransform; keep for backwards compatibility var
            scale = baseScale * (1 + level / ZOOM_DIVISOR);
            applyTransform();
            saveEditorState(); // Save state on change
        });
        // initial bubble position on load
        positionZoomBubble();
        window.addEventListener('resize', function() {
            positionZoomBubble();
            // recompute base scales on resize to maintain exact mirroring
            baseScale = computeBaseScaleForPreview();
            computeBaseScalesForThumbs();
            applyTransform();
        });
    }

    if (zoomOutBtn && zoomInput) {
        zoomOutBtn.addEventListener('click', function() {
            const current = parseInt(zoomInput.value || '0', 10);
            const next = Math.max(0, current - 1);
            zoomInput.value = String(next);
            scale = baseScale * (1 + next / ZOOM_DIVISOR);
            applyTransform();
            saveEditorState(); // Save state on change
        });
    }

    if (zoomInBtn && zoomInput) {
        zoomInBtn.addEventListener('click', function() {
            const current = parseInt(zoomInput.value || '0', 10);
            const next = Math.min(20, current + 1);
            zoomInput.value = String(next);
            scale = baseScale * (1 + next / ZOOM_DIVISOR);
            applyTransform();
            saveEditorState(); // Save state on change
        });
    }

    // Initialize base scale for existing images on load
    if (hasPreviewImage()) {
        const init = function() {
            if (previewImg.naturalWidth && previewImg.naturalHeight) {
                baseScale = computeBaseScaleForPreview();
                computeBaseScalesForThumbs();
                
                // For uploaded profile pictures, always start at zoom 0 (they're already processed)
                // Only restore state for new files being edited
                if (isUploadedProfilePicture(previewImg.src)) {
                    // Reset to defaults for uploaded images
                    rotation = 0;
                    imgOffsetX = 0;
                    imgOffsetY = 0;
                    if (zoomInput) zoomInput.value = '0';
                } else {
                    // Try to restore saved state for new files being edited
                    const restored = restoreEditorState(previewImg.src);
                    if (!restored) {
                        // If no saved state, reset to defaults
                        rotation = 0;
                        imgOffsetX = 0;
                        imgOffsetY = 0;
                        if (zoomInput) zoomInput.value = '0';
                    }
                }
                
                applyTransform();
                if (zoomValue && zoomInput) {
                    positionZoomBubble();
                }
            }
        };
        if (previewImg.complete && previewImg.naturalWidth > 0) {
            init();
        } else {
            previewImg.onload = init;
        }
    } else {
        // ensure controls hidden when no image at startup
        syncControlsVisibility();
    }

    if (rotateBtn) {
        rotateBtn.addEventListener('click', function() {
            rotation = (rotation + 90) % 360;
            applyTransform();
            saveEditorState(); // Save state on change
        });
    }

    // Function to capture the cropped image as canvas
    function captureCroppedImage() {
        if (!previewImg || !cropperEl || !hasPreviewImage()) {
            return null;
        }

        const canvas = document.createElement('canvas');
        const ctx = canvas.getContext('2d');
        const cropperSize = cropperEl.clientWidth;
        
        // Use higher resolution for better quality
        const scale = 2; // 2x for retina displays
        canvas.width = cropperSize * scale;
        canvas.height = cropperSize * scale;
        
        // Scale the context to match the canvas scale
        ctx.scale(scale, scale);
        
        // Get current transform values
        const level = zoomInput ? parseInt(zoomInput.value || '0', 10) : 0;
        const zoomFactor = 1 + (isNaN(level) ? 0 : level) / ZOOM_DIVISOR;
        const mainScale = baseScale * zoomFactor;
        
        // Create an off-screen image to apply transforms
        const img = new Image();
        img.crossOrigin = 'anonymous';
        img.src = previewImg.src;
        
        return new Promise(function(resolve, reject) {
            if (img.complete && img.naturalWidth > 0) {
                drawImage();
            } else {
                img.onload = function() {
                    if (img.naturalWidth > 0) {
                        drawImage();
                    } else {
                        reject(new Error('Failed to load image'));
                    }
                };
                img.onerror = function() { reject(new Error('Failed to load image')); };
            }
            
            function drawImage() {
                try {
                    // Calculate the center position
                    const centerX = cropperSize / 2;
                    const centerY = cropperSize / 2;
                    
                    // Save context
                    ctx.save();
                    
                    // Create circular clipping path
                    ctx.beginPath();
                    ctx.arc(centerX, centerY, cropperSize / 2, 0, Math.PI * 2);
                    ctx.clip();
                    
                    // Move to center and apply rotation (matching CSS transform)
                    ctx.translate(centerX, centerY);
                    ctx.rotate((rotation * Math.PI) / 180);
                    
                    // Calculate scaled dimensions
                    const scaledW = img.naturalWidth * mainScale;
                    const scaledH = img.naturalHeight * mainScale;
                    
                    // Draw image centered, then offset (matching CSS: translate(calc(-50% + offsetX), calc(-50% + offsetY)))
                    ctx.drawImage(img, 
                        -scaledW / 2 + imgOffsetX, 
                        -scaledH / 2 + imgOffsetY, 
                        scaledW, 
                        scaledH
                    );
                    
                    // Restore context
                    ctx.restore();
                    
                    // Convert canvas to base64 data URL
                    const dataUrl = canvas.toDataURL('image/png', 0.95);
                    resolve(dataUrl);
                } catch (error) {
                    reject(error);
                }
            }
        });
    }

    if (saveBtn) {
        saveBtn.addEventListener('click', async function() {
            if (!hasPreviewImage()) {
                alert('Please select an image first.');
                return;
            }

            // Disable save button during upload
            const originalText = saveBtn.textContent;
            saveBtn.disabled = true;
            saveBtn.textContent = 'Saving...';

            try {
                // Capture the cropped image
                const imageDataUrl = await captureCroppedImage();
                
                if (!imageDataUrl) {
                    throw new Error('Failed to capture image');
                }

                // Get CSRF token if available
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
                
                // Send to server
                const formData = new FormData();
                formData.append('image', imageDataUrl);
                if (csrfToken) {
                    formData.append('_token', csrfToken);
                }

                const response = await fetch('/designer/profile/upload-photo', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });

                const result = await response.json();

                if (result.success) {
                    // Clear saved state since we're saving a new version
                    localStorage.removeItem('profilePictureEditorState');
                    // Reload the page to show the new profile picture
                    window.location.reload();
                } else {
                    alert('Failed to upload profile picture: ' + (result.message || 'Unknown error'));
                    saveBtn.disabled = false;
                    saveBtn.textContent = originalText;
                }
            } catch (error) {
                console.error('Error uploading profile picture:', error);
                alert('An error occurred while uploading your profile picture. Please try again.');
                saveBtn.disabled = false;
                saveBtn.textContent = originalText;
            }
        });
    }
    const searchInput = document.getElementById('designerSearchInput');
    
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            const query = this.value.trim();
            
            // TODO: Implement designer-specific search functionality
            // This could search projects, clients, designs, etc.
            console.log('Designer search query:', query);
        });
    }

    // Edit Bio Modal logic
    const openBioBtn = document.getElementById('openEditBio');
    const bioModal = document.getElementById('editBioModal');
    const closeBioBtn = document.getElementById('closeEditBio');
    const bioBackdrop = bioModal ? bioModal.querySelector('.designer-bio-modal__backdrop') : null;
    const bioTextarea = document.getElementById('editBioTextarea');
    const bioSaveBtn = document.getElementById('editBioSave');
    const bioContent = document.getElementById('bioContent');

    function openBioModal() {
        if (bioModal) {
            try {
                bioModal.classList.remove('hidden');
                bioModal.classList.remove('closing');
                // Focus the textarea
                if (bioTextarea) {
                    setTimeout(function() {
                        bioTextarea.focus();
                    }, 100);
                }
            } catch (e) {
                console.error('Error opening bio modal:', e);
            }
        }
    }

    function closeBioModal() {
        if (bioModal && !bioModal.classList.contains('closing')) {
            bioModal.classList.add('closing');
            setTimeout(function() {
                if (bioModal) {
                    bioModal.classList.add('hidden');
                    bioModal.classList.remove('closing');
                }
            }, 250);
        }
    }

    if (openBioBtn && bioModal) {
        openBioBtn.addEventListener('click', function(e) {
            e.preventDefault();
            openBioModal();
        });
    }
    if (closeBioBtn) {
        closeBioBtn.addEventListener('click', closeBioModal);
    }
    if (bioBackdrop) {
        bioBackdrop.addEventListener('click', closeBioModal);
    }

    // Handle Escape key to close modal
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && bioModal && !bioModal.classList.contains('hidden')) {
            closeBioModal();
        }
    });

    // Save bio functionality
    if (bioSaveBtn && bioTextarea) {
        bioSaveBtn.addEventListener('click', async function() {
            const originalText = bioSaveBtn.textContent;
            bioSaveBtn.disabled = true;
            bioSaveBtn.textContent = 'Saving...';

            try {
                const bioValue = bioTextarea.value.trim();
                
                const response = await fetch('/designer/profile/update-bio', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({
                        bio: bioValue
                    })
                });

                const result = await response.json();

                if (result.success) {
                    // Reload page to show updated bio with proper conditional rendering
                    window.location.reload();
                } else {
                    alert('Failed to update bio: ' + (result.message || 'Unknown error'));
                    bioSaveBtn.disabled = false;
                    bioSaveBtn.textContent = originalText;
                }
            } catch (error) {
                console.error('Error updating bio:', error);
                alert('An error occurred while updating your bio. Please try again.');
                bioSaveBtn.disabled = false;
                bioSaveBtn.textContent = originalText;
            }
        });
    }
});

// Projects Page - Project Detail Modal
document.addEventListener('DOMContentLoaded', function() {
    initProjectDetailModal();
});

function initProjectDetailModal() {
    const projectModal = document.getElementById('projectDetailModal');
    const closeBtn = document.getElementById('closeProjectDetail');
    const backdrop = projectModal ? projectModal.querySelector('.project-modal__backdrop') : null;
    const contentDiv = document.getElementById('projectDetailContent');
    const viewProjectBtns = document.querySelectorAll('.view-project-btn');

    if (!projectModal || !contentDiv) return;

    function openProjectModal(projectId) {
        if (!projectId) return;
        
        // Show loading state immediately
        contentDiv.innerHTML = '<div style="text-align: center; padding: 3rem 2rem;"><div style="display: inline-block; width: 40px; height: 40px; border: 4px solid #f3f3f3; border-top: 4px solid var(--color-primary); border-radius: 50%; animation: spin 1s linear infinite;"></div><p style="margin-top: 1rem; font-family: \'Montserrat\', Arial, sans-serif; color: #6b7280; font-size: 14px;">Loading project details...</p></div>';
        
        // Show modal immediately - remove hidden class and ensure it's visible
        projectModal.classList.remove('hidden');
        projectModal.classList.remove('closing');
        projectModal.setAttribute('aria-hidden', 'false');
        
        // Prevent body scroll
        document.body.style.overflow = 'hidden';
        
        // Force display (in case CSS is causing issues)
        projectModal.style.display = 'flex';
        
        // Fetch project details
        fetch(`/designer/projects/${projectId}?modal=1`, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Failed to load project details');
            }
            return response.text();
        })
        .then(html => {
            contentDiv.innerHTML = html;
            
            // Re-attach event listeners for buttons inside modal
            const submitProposalBtn = document.getElementById('submitProposalBtn');
            if (submitProposalBtn) {
                submitProposalBtn.addEventListener('click', function() {
                    const projectId = this.getAttribute('data-project-id');
                    if (projectId) {
                        openSubmitProposalModal(projectId);
                    }
                });
            }
        })
        .catch(error => {
            console.error('Error loading project:', error);
            contentDiv.innerHTML = '<div style="text-align: center; padding: 3rem 2rem;"><div style="font-size: 3rem; color: #ef4444; margin-bottom: 1rem;"><i class="bi bi-exclamation-triangle"></i></div><p style="font-family: \'Montserrat\', Arial, sans-serif; color: #ef4444; font-size: 14px; margin-bottom: 0.5rem;">Failed to load project details.</p><p style="font-family: \'Montserrat\', Arial, sans-serif; color: #6b7280; font-size: 13px;">Please try again.</p></div>';
        });
    }

    function closeProjectModal() {
        if (projectModal && !projectModal.classList.contains('closing')) {
            projectModal.classList.add('closing');
            projectModal.setAttribute('aria-hidden', 'true');
            
            // Restore body scroll
            document.body.style.overflow = '';
            
            setTimeout(function() {
                if (projectModal) {
                    projectModal.classList.add('hidden');
                    projectModal.classList.remove('closing');
                    projectModal.style.display = '';
                    contentDiv.innerHTML = '';
                }
            }, 250);
        }
    }

    // Attach event listeners to view buttons
    viewProjectBtns.forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const projectId = this.getAttribute('data-project-id');
            if (projectId) {
                openProjectModal(projectId);
            }
        });
    });

    if (closeBtn) {
        closeBtn.addEventListener('click', closeProjectModal);
    }

    if (backdrop) {
        backdrop.addEventListener('click', closeProjectModal);
    }

    // Handle Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && projectModal && !projectModal.classList.contains('hidden')) {
            closeProjectModal();
        }
    });
}

// Submit Proposal Modal
function initSubmitProposalModal() {
    const proposalModal = document.getElementById('submitProposalModal');
    const closeBtn = document.getElementById('closeSubmitProposal');
    const cancelBtn = document.getElementById('cancelSubmitProposal');
    const backdrop = proposalModal ? proposalModal.querySelector('.project-modal__backdrop') : null;
    const form = document.getElementById('submitProposalForm');
    const submitBtn = document.getElementById('submitProposalFormBtn');
    const projectIdInput = document.getElementById('proposalProjectId');

    if (!proposalModal || !form) return;

    window.openSubmitProposalModal = function(projectId) {
        if (!projectId) return;
        
        // Set project ID in hidden input
        if (projectIdInput) {
            projectIdInput.value = projectId;
        }
        
        // Reset form
        form.reset();
        if (projectIdInput) {
            projectIdInput.value = projectId;
        }
        
        // Show modal
        proposalModal.classList.remove('hidden');
        proposalModal.classList.remove('closing');
        proposalModal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
        proposalModal.style.display = 'flex';
        
        // Focus first input
        const firstInput = form.querySelector('textarea, input[type="number"]');
        if (firstInput) {
            setTimeout(() => firstInput.focus(), 100);
        }
    };

    function closeSubmitProposalModal() {
        if (proposalModal && !proposalModal.classList.contains('closing')) {
            proposalModal.classList.add('closing');
            proposalModal.setAttribute('aria-hidden', 'true');
            document.body.style.overflow = '';
            
            setTimeout(function() {
                if (proposalModal) {
                    proposalModal.classList.add('hidden');
                    proposalModal.classList.remove('closing');
                    proposalModal.style.display = '';
                    form.reset();
                }
            }, 250);
        }
    }

    if (closeBtn) {
        closeBtn.addEventListener('click', closeSubmitProposalModal);
    }

    if (cancelBtn) {
        cancelBtn.addEventListener('click', closeSubmitProposalModal);
    }

    if (backdrop) {
        backdrop.addEventListener('click', closeSubmitProposalModal);
    }

    // Handle Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && proposalModal && !proposalModal.classList.contains('hidden')) {
            closeSubmitProposalModal();
        }
    });

    // Form submission
    if (form && submitBtn) {
        form.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const projectId = projectIdInput ? projectIdInput.value : null;
            if (!projectId) {
                alert('Project ID is missing. Please refresh the page and try again.');
                return;
            }

            // Disable submit button
            submitBtn.disabled = true;
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="bi bi-hourglass-split"></i> <span>Submitting...</span>';

            // Get form data
            const coverLetterEl = document.getElementById('coverLetter');
            const proposalTextEl = document.getElementById('proposalText');
            const proposedPriceEl = document.getElementById('proposedPrice');
            const deliveryTimeEl = document.getElementById('deliveryTime');
            const revisionRoundsEl = document.getElementById('revisionRounds');
            
            // Validate required fields
            if (!proposalTextEl || !proposalTextEl.value.trim()) {
                alert('Proposal text is required.');
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
                return;
            }
            
            const proposedPrice = parseFloat(proposedPriceEl.value);
            if (isNaN(proposedPrice) || proposedPrice <= 0) {
                alert('Proposed price must be a positive number.');
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
                return;
            }
            
            const deliveryTime = parseInt(deliveryTimeEl.value);
            if (isNaN(deliveryTime) || deliveryTime <= 0) {
                alert('Delivery time must be a positive number of days.');
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
                return;
            }
            
            const formData = {
                projectId: projectId,
                coverLetter: coverLetterEl ? coverLetterEl.value.trim() : '',
                proposalText: proposalTextEl.value.trim(),
                proposedPrice: proposedPrice,
                deliveryTime: deliveryTime,
                revisionRounds: revisionRoundsEl ? (parseInt(revisionRoundsEl.value) || 1) : 1
            };

            try {
                const response = await fetch(`/designer/projects/${projectId}/proposal/create`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify(formData),
                    credentials: 'same-origin'
                });
                
                // Check if response is ok
                if (!response.ok) {
                    // Try to parse error response
                    let errorMessage = 'Failed to submit proposal. Please try again.';
                    try {
                        const errorData = await response.json();
                        errorMessage = errorData.message || errorMessage;
                    } catch (e) {
                        // If not JSON, use status text
                        errorMessage = `Error ${response.status}: ${response.statusText}`;
                    }
                    throw new Error(errorMessage);
                }

                const result = await response.json();
                
                // Log for debugging
                console.log('Proposal submission response:', result);

                if (result.success) {
                    // Close submit proposal modal
                    closeSubmitProposalModal();
                    
                    // Close project detail modal if open
                    const projectModal = document.getElementById('projectDetailModal');
                    if (projectModal && !projectModal.classList.contains('hidden')) {
                        const closeProjectModal = projectModal.querySelector('#closeProjectDetail');
                        if (closeProjectModal) {
                            closeProjectModal.click();
                        }
                    }
                    
                    // Show success modal - ensure it's available
                    if (typeof window.showProposalSuccessModal === 'function') {
                        window.showProposalSuccessModal(result.message || 'Your proposal has been submitted and is now pending review by the client.');
                    } else {
                        // Fallback if modal function is not available
                        alert('Success! ' + (result.message || 'Your proposal has been submitted successfully.'));
                        window.location.reload();
                    }
                } else {
                    alert('Error: ' + (result.message || 'Failed to submit proposal. Please try again.'));
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalText;
                }
            } catch (error) {
                console.error('Error submitting proposal:', error);
                let errorMessage = 'An error occurred while submitting your proposal. Please try again.';
                
                // Check if it's a JSON parse error (might be HTML error page)
                if (error instanceof SyntaxError) {
                    errorMessage = 'Server error occurred. Please check your connection and try again.';
                } else if (error.message) {
                    errorMessage = error.message;
                }
                
                alert(errorMessage);
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            }
        });
    }
}

// Success Modal
function initProposalSuccessModal() {
    const successModal = document.getElementById('proposalSuccessModal');
    const closeBtn = document.getElementById('closeSuccessModal');
    const backdrop = successModal ? successModal.querySelector('.project-modal__backdrop') : null;

    if (!successModal) return;

    window.showProposalSuccessModal = function(customMessage) {
        // Update message if provided
        const messageEl = successModal.querySelector('#successModalMessage');
        if (messageEl) {
            if (customMessage) {
                messageEl.textContent = customMessage;
            } else {
                messageEl.textContent = 'Your proposal has been submitted and is now pending review by the client.';
            }
        }
        
        successModal.classList.remove('hidden');
        successModal.classList.remove('closing');
        successModal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
        successModal.style.display = 'flex';
    };

    function closeSuccessModal(shouldReload = true) {
        if (successModal && !successModal.classList.contains('closing')) {
            successModal.classList.add('closing');
            successModal.setAttribute('aria-hidden', 'true');
            document.body.style.overflow = '';
            
            setTimeout(function() {
                if (successModal) {
                    successModal.classList.add('hidden');
                    successModal.classList.remove('closing');
                    successModal.style.display = '';
                    
                    // Reload page if needed (e.g., after submitting new proposal)
                    if (shouldReload) {
                        window.location.reload();
                    }
                }
            }, 250);
        }
    }
    
    window.closeProposalSuccessModal = closeSuccessModal;

    if (closeBtn) {
        closeBtn.addEventListener('click', closeSuccessModal);
    }

    if (backdrop) {
        backdrop.addEventListener('click', closeSuccessModal);
    }

    // Handle Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && successModal && !successModal.classList.contains('hidden')) {
            closeSuccessModal();
        }
    });
}

// View Proposal Modal
function initViewProposalModal() {
    const viewModal = document.getElementById('viewProposalModal');
    const closeBtn = document.getElementById('closeViewProposal');
    const backdrop = viewModal ? viewModal.querySelector('.project-modal__backdrop') : null;
    const contentDiv = document.getElementById('viewProposalContent');
    const viewProposalBtns = document.querySelectorAll('.view-proposal-btn');

    if (!viewModal || !contentDiv) return;

    function openViewProposalModal(proposalId) {
        if (!proposalId) return;
        
        // Show loading state
        contentDiv.innerHTML = '<div style="text-align: center; padding: 3rem 2rem;"><div style="display: inline-block; width: 40px; height: 40px; border: 4px solid #f3f3f3; border-top: 4px solid var(--color-primary); border-radius: 50%; animation: spin 1s linear infinite;"></div><p style="margin-top: 1rem; font-family: \'Montserrat\', Arial, sans-serif; color: #6b7280; font-size: 14px;">Loading proposal details...</p></div>';
        
        // Show modal
        viewModal.classList.remove('hidden');
        viewModal.classList.remove('closing');
        viewModal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
        viewModal.style.display = 'flex';
        
        // Fetch proposal details
        fetch(`/designer/proposals/${proposalId}?modal=1`, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Failed to load proposal details');
            }
            return response.text();
        })
        .then(html => {
            contentDiv.innerHTML = html;
            
            // Re-attach event listener for edit button inside modal
            const editBtn = contentDiv.querySelector('.btn-edit-proposal-modal');
            if (editBtn) {
                editBtn.addEventListener('click', function() {
                    const proposalId = this.getAttribute('data-proposal-id');
                    if (proposalId) {
                        closeViewProposalModal();
                        setTimeout(() => openEditProposalModal(proposalId), 300);
                    }
                });
            }
            
            // Re-attach event listener for delete button inside modal
            const deleteBtn = contentDiv.querySelector('.btn-delete-proposal-modal');
            if (deleteBtn) {
                deleteBtn.addEventListener('click', function() {
                    const proposalId = this.getAttribute('data-proposal-id');
                    const projectTitle = this.getAttribute('data-proposal-project') || 'this project';
                    if (proposalId) {
                        // Close view modal first
                        closeViewProposalModal();
                        // Show delete confirmation modal
                        setTimeout(() => {
                            if (typeof window.showDeleteProposalConfirmModal === 'function') {
                                window.showDeleteProposalConfirmModal(proposalId, projectTitle);
                            } else {
                                handleDeleteProposal(proposalId, projectTitle);
                            }
                        }, 300);
                    }
                });
            }
        })
        .catch(error => {
            console.error('Error loading proposal:', error);
            contentDiv.innerHTML = '<div style="text-align: center; padding: 3rem 2rem;"><div style="font-size: 3rem; color: #ef4444; margin-bottom: 1rem;"><i class="bi bi-exclamation-triangle"></i></div><p style="font-family: \'Montserrat\', Arial, sans-serif; color: #ef4444; font-size: 14px; margin-bottom: 0.5rem;">Failed to load proposal details.</p><p style="font-family: \'Montserrat\', Arial, sans-serif; color: #6b7280; font-size: 13px;">Please try again.</p></div>';
        });
    }

    function closeViewProposalModal() {
        if (viewModal && !viewModal.classList.contains('closing')) {
            viewModal.classList.add('closing');
            viewModal.setAttribute('aria-hidden', 'true');
            document.body.style.overflow = '';
            
            setTimeout(function() {
                if (viewModal) {
                    viewModal.classList.add('hidden');
                    viewModal.classList.remove('closing');
                    viewModal.style.display = '';
                    contentDiv.innerHTML = '';
                }
            }, 250);
        }
    }

    // Attach event listeners to view buttons
    viewProposalBtns.forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const proposalId = this.getAttribute('data-proposal-id');
            if (proposalId) {
                openViewProposalModal(proposalId);
            }
        });
    });

    if (closeBtn) {
        closeBtn.addEventListener('click', closeViewProposalModal);
    }

    if (backdrop) {
        backdrop.addEventListener('click', closeViewProposalModal);
    }

    // Handle Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && viewModal && !viewModal.classList.contains('hidden')) {
            closeViewProposalModal();
        }
    });
}

// Edit Proposal Modal
function initEditProposalModal() {
    const editModal = document.getElementById('editProposalModal');
    const closeBtn = document.getElementById('closeEditProposal');
    const cancelBtn = document.getElementById('cancelEditProposal');
    const backdrop = editModal ? editModal.querySelector('.project-modal__backdrop') : null;
    const contentDiv = document.getElementById('editProposalContent');
    const editProposalBtns = document.querySelectorAll('.edit-proposal-btn');
    const form = document.getElementById('editProposalForm');
    const submitBtn = document.getElementById('submitEditProposalBtn');

    if (!editModal || !contentDiv) return;

    window.openEditProposalModal = function(proposalId) {
        if (!proposalId) return;
        
        // Show loading state
        contentDiv.innerHTML = '<div style="text-align: center; padding: 3rem 2rem;"><div style="display: inline-block; width: 40px; height: 40px; border: 4px solid #f3f3f3; border-top: 4px solid var(--color-primary); border-radius: 50%; animation: spin 1s linear infinite;"></div><p style="margin-top: 1rem; font-family: \'Montserrat\', Arial, sans-serif; color: #6b7280; font-size: 14px;">Loading proposal...</p></div>';
        
        // Show modal
        editModal.classList.remove('hidden');
        editModal.classList.remove('closing');
        editModal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
        editModal.style.display = 'flex';
        
        // Fetch proposal for editing
        fetch(`/designer/proposals/${proposalId}/edit?modal=1`, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => {
            if (!response.ok) {
                return response.json().then(data => {
                    throw new Error(data.message || 'Failed to load proposal');
                });
            }
            return response.text();
        })
        .then(html => {
            contentDiv.innerHTML = html;
            
            // Re-attach form submission handler
            const editForm = document.getElementById('editProposalForm');
            const editSubmitBtn = document.getElementById('submitEditProposalBtn');
            const cancelEditBtn = document.getElementById('cancelEditProposal');
            
            if (editForm) {
                editForm.addEventListener('submit', handleEditProposalSubmit);
            }
            
            // Also attach click handler to submit button (since it's outside the form)
            if (editSubmitBtn) {
                editSubmitBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    if (editForm) {
                        // Trigger form validation
                        if (editForm.checkValidity()) {
                            handleEditProposalSubmit({ preventDefault: () => {}, target: editForm });
                        } else {
                            editForm.reportValidity();
                        }
                    }
                });
            }
            
            // Re-attach cancel button handler
            if (cancelEditBtn) {
                cancelEditBtn.addEventListener('click', closeEditProposalModal);
            }
            
            // Focus first input
            const firstInput = editForm ? editForm.querySelector('textarea, input[type="number"]') : null;
            if (firstInput) {
                setTimeout(() => firstInput.focus(), 100);
            }
        })
        .catch(error => {
            console.error('Error loading proposal:', error);
            contentDiv.innerHTML = `<div style="text-align: center; padding: 3rem 2rem;"><div style="font-size: 3rem; color: #ef4444; margin-bottom: 1rem;"><i class="bi bi-exclamation-triangle"></i></div><p style="font-family: 'Montserrat', Arial, sans-serif; color: #ef4444; font-size: 14px; margin-bottom: 0.5rem;">${error.message || 'Failed to load proposal.'}</p><p style="font-family: 'Montserrat', Arial, sans-serif; color: #6b7280; font-size: 13px;">Please try again.</p></div>`;
        });
    };

    function closeEditProposalModal() {
        if (editModal && !editModal.classList.contains('closing')) {
            editModal.classList.add('closing');
            editModal.setAttribute('aria-hidden', 'true');
            document.body.style.overflow = '';
            
            setTimeout(function() {
                if (editModal) {
                    editModal.classList.add('hidden');
                    editModal.classList.remove('closing');
                    editModal.style.display = '';
                    contentDiv.innerHTML = '';
                }
            }, 250);
        }
    }

    function handleEditProposalSubmit(e) {
        if (e && e.preventDefault) {
            e.preventDefault();
        }
        
        const form = document.getElementById('editProposalForm');
        if (!form) {
            alert('Form not found. Please refresh the page and try again.');
            return;
        }
        
        const proposalIdInput = form.querySelector('#editProposalId');
        const proposalId = proposalIdInput ? proposalIdInput.value : null;
        
        if (!proposalId) {
            alert('Proposal ID is missing. Please refresh the page and try again.');
            return;
        }

        const submitBtn = document.getElementById('submitEditProposalBtn');
        if (!submitBtn) {
            console.error('Submit button not found');
            return;
        }
        
        // Disable submit button
        submitBtn.disabled = true;
        const originalText = submitBtn.innerHTML;
        submitBtn.innerHTML = '<i class="bi bi-hourglass-split"></i> <span>Updating...</span>';

        // Get form data
        const formData = {
            coverLetter: document.getElementById('editCoverLetter').value.trim(),
            proposalText: document.getElementById('editProposalText').value.trim(),
            proposedPrice: parseFloat(document.getElementById('editProposedPrice').value),
            deliveryTime: parseInt(document.getElementById('editDeliveryTime').value),
            revisionRounds: parseInt(document.getElementById('editRevisionRounds').value) || 1
        };

        fetch(`/designer/proposals/${proposalId}/update`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify(formData)
        })
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                // Close edit modal
                closeEditProposalModal();
                
                // Show success message (don't auto-reload, let user close manually)
                setTimeout(() => {
                    showProposalSuccessModal('Proposal updated successfully!');
                    // Override close to reload after showing success
                    const originalClose = window.closeProposalSuccessModal;
                    window.closeProposalSuccessModal = function() {
                        originalClose(false);
                        window.location.reload();
                    };
                }, 300);
            } else {
                alert('Error: ' + (result.message || 'Failed to update proposal. Please try again.'));
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            }
        })
        .catch(error => {
            console.error('Error updating proposal:', error);
            alert('An error occurred while updating your proposal. Please try again.');
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
        });
    }

    // Attach event listeners to edit buttons
    editProposalBtns.forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const proposalId = this.getAttribute('data-proposal-id');
            if (proposalId) {
                openEditProposalModal(proposalId);
            }
        });
    });

    if (closeBtn) {
        closeBtn.addEventListener('click', closeEditProposalModal);
    }

    if (cancelBtn) {
        cancelBtn.addEventListener('click', closeEditProposalModal);
    }

    if (backdrop) {
        backdrop.addEventListener('click', closeEditProposalModal);
    }

    // Handle Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && editModal && !editModal.classList.contains('hidden')) {
            closeEditProposalModal();
        }
    });
}

// Delete Proposal Confirmation Modal
function initDeleteProposalConfirmModal() {
    const confirmModal = document.getElementById('deleteProposalConfirmModal');
    const cancelBtn = document.getElementById('cancelDeleteProposal');
    const confirmBtn = document.getElementById('confirmDeleteProposal');
    const backdrop = confirmModal ? confirmModal.querySelector('.project-modal__backdrop') : null;
    const projectTitleEl = document.getElementById('deleteConfirmProjectTitle');

    if (!confirmModal) return;

    let currentProposalId = null;
    let currentProjectTitle = null;

    window.showDeleteProposalConfirmModal = function(proposalId, projectTitle) {
        if (!proposalId) return;
        
        currentProposalId = proposalId;
        currentProjectTitle = projectTitle || 'this project';
        
        // Update project title in modal
        if (projectTitleEl) {
            projectTitleEl.textContent = currentProjectTitle;
        }
        
        // Show modal
        confirmModal.classList.remove('hidden');
        confirmModal.classList.remove('closing');
        confirmModal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
        confirmModal.style.display = 'flex';
    };

    function closeDeleteConfirmModal() {
        if (confirmModal && !confirmModal.classList.contains('closing')) {
            confirmModal.classList.add('closing');
            confirmModal.setAttribute('aria-hidden', 'true');
            document.body.style.overflow = '';
            
            setTimeout(function() {
                if (confirmModal) {
                    confirmModal.classList.add('hidden');
                    confirmModal.classList.remove('closing');
                    confirmModal.style.display = '';
                    currentProposalId = null;
                    currentProjectTitle = null;
                }
            }, 250);
        }
    }

    // Cancel button
    if (cancelBtn) {
        cancelBtn.addEventListener('click', closeDeleteConfirmModal);
    }

    // Confirm button
    if (confirmBtn) {
        confirmBtn.addEventListener('click', function() {
            if (!currentProposalId) return;
            
            // Disable buttons
            confirmBtn.disabled = true;
            cancelBtn.disabled = true;
            const originalConfirmText = confirmBtn.innerHTML;
            confirmBtn.innerHTML = '<i class="bi bi-hourglass-split"></i> <span>Deleting...</span>';
            
            // Close modal
            closeDeleteConfirmModal();
            
            // Show loading state on delete buttons
            const deleteBtns = document.querySelectorAll(`.delete-proposal-btn[data-proposal-id="${currentProposalId}"]`);
            deleteBtns.forEach(btn => {
                btn.disabled = true;
                btn.innerHTML = 'Deleting...';
            });
            
            // Delete proposal
            fetch(`/designer/proposals/${currentProposalId}/delete`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin'
            })
            .then(response => {
                if (!response.ok) {
                    return response.json().then(data => {
                        throw new Error(data.message || 'Failed to delete proposal');
                    });
                }
                return response.json();
            })
            .then(result => {
                if (result.success) {
                    // Close view modal if open
                    const viewModal = document.getElementById('viewProposalModal');
                    if (viewModal && !viewModal.classList.contains('hidden')) {
                        const closeBtn = document.getElementById('closeViewProposal');
                        if (closeBtn) {
                            closeBtn.click();
                        }
                    }
                    
                    // Remove proposal card from DOM
                    const proposalCard = document.querySelector(`[data-proposal-id="${currentProposalId}"]`);
                    if (proposalCard && proposalCard.closest('.project-card')) {
                        proposalCard.closest('.project-card').remove();
                    }
                    
                    // Reload page to reflect changes
                    window.location.reload();
                } else {
                    throw new Error(result.message || 'Failed to delete proposal');
                }
            })
            .catch(error => {
                console.error('Error deleting proposal:', error);
                alert('Error: ' + (error.message || 'Failed to delete proposal. Please try again.'));
                
                // Re-enable buttons
                confirmBtn.disabled = false;
                cancelBtn.disabled = false;
                confirmBtn.innerHTML = originalConfirmText;
                
                deleteBtns.forEach(btn => {
                    btn.disabled = false;
                    btn.innerHTML = 'Delete';
                });
            });
        });
    }

    // Backdrop click
    if (backdrop) {
        backdrop.addEventListener('click', closeDeleteConfirmModal);
    }

    // Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && confirmModal && !confirmModal.classList.contains('hidden')) {
            closeDeleteConfirmModal();
        }
    });
}

// Delete Proposal Handler
function handleDeleteProposal(proposalId, projectTitle) {
    if (!proposalId) return;
    
    // Show custom confirmation modal
    if (typeof window.showDeleteProposalConfirmModal === 'function') {
        window.showDeleteProposalConfirmModal(proposalId, projectTitle);
    } else {
        // Fallback to browser confirm
        const confirmed = confirm(`Are you sure you want to delete your proposal for "${projectTitle}"?\n\nThis action cannot be undone.`);
        if (confirmed) {
            // Trigger delete directly (fallback behavior)
            const deleteBtns = document.querySelectorAll(`.delete-proposal-btn[data-proposal-id="${proposalId}"]`);
            if (deleteBtns.length > 0) {
                deleteBtns[0].click();
            }
        }
    }
}

// Initialize delete proposal buttons
function initDeleteProposalButtons() {
    const deleteBtns = document.querySelectorAll('.delete-proposal-btn');
    deleteBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const proposalId = this.getAttribute('data-proposal-id');
            const projectTitle = this.getAttribute('data-proposal-project') || 'this project';
            if (proposalId) {
                handleDeleteProposal(proposalId, projectTitle);
            }
        });
    });
}

// Initialize submit proposal modal on page load
document.addEventListener('DOMContentLoaded', function() {
    initSubmitProposalModal();
    initProposalSuccessModal();
    initViewProposalModal();
    initEditProposalModal();
    initDeleteProposalConfirmModal();
    initDeleteProposalButtons();
});

// Spin animation is now in designer.css

// Notification bell click handler
document.addEventListener('DOMContentLoaded', function() {
    const bellButton = document.querySelector('.designer-icon.bell');
    
    if (bellButton) {
        bellButton.addEventListener('click', function() {
            // TODO: Implement notification functionality
            console.log('Notification bell clicked');
        });
    }
    
    // Messages Page Functionality
    initDesignerMessagesPage();
});

// Messages Page Initialization
function initDesignerMessagesPage() {
    const chatInput = document.querySelector('.designer-chat-input');
    const chatSendBtn = document.querySelector('.designer-chat-send-btn');
    const chatMessages = document.querySelector('.designer-chat-messages');
    const conversationItems = document.querySelectorAll('.designer-conversation-item');
    
    // Auto-resize textarea
    if (chatInput) {
        // Set placeholder
        if (!chatInput.getAttribute('placeholder')) {
            chatInput.setAttribute('placeholder', 'Send a message...');
        }
        
        // Auto-resize function
        function autoResize() {
            // Reset height to auto to get accurate scrollHeight
            chatInput.style.height = 'auto';
            // Calculate the desired height, but don't exceed max-height
            const maxHeight = 100; // matches CSS max-height
            const scrollHeight = chatInput.scrollHeight;
            
            // If content exceeds max-height, set height to max-height and let scrollbar appear
            if (scrollHeight > maxHeight) {
                chatInput.style.height = maxHeight + 'px';
                chatInput.style.overflowY = 'auto';
                // Scroll to bottom when content exceeds max height
                setTimeout(function() {
                    chatInput.scrollTop = chatInput.scrollHeight;
                }, 0);
            } else {
                // Otherwise, grow with content
                chatInput.style.height = Math.max(40, scrollHeight) + 'px';
                chatInput.style.overflowY = 'hidden';
            }
        }
        
        chatInput.addEventListener('input', autoResize);
        
        // Also handle scroll to bottom on focus to ensure latest content is visible
        chatInput.addEventListener('focus', function() {
            if (chatInput.scrollHeight > chatInput.clientHeight) {
                chatInput.scrollTop = chatInput.scrollHeight;
            }
        });
        
        // Initial resize
        autoResize();
        
        // Handle Enter key (send) and Shift+Enter (new line)
        chatInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                sendMessage();
            }
        });
    }
    
    // Send message function
    function sendMessage() {
        if (!chatInput || !chatMessages) return;
        
        const messageText = chatInput.value.trim();
        if (!messageText) return;
        
        // Get current user info (could be from data attributes or user context)
        const currentUserInitials = 'AS'; // This should come from user context
        const currentUserName = 'Aljemark Sendiong'; // This should come from user context
        
        // Create message element
        const messageElement = createMessageElement({
            sender: currentUserName,
            initials: currentUserInitials,
            text: messageText,
            time: getCurrentTime(),
            isFromUser: true
        });
        
        // Add message to chat
        chatMessages.appendChild(messageElement);
        
        // Clear input and reset height
        chatInput.value = '';
        chatInput.style.height = 'auto';
        
        // Scroll to bottom
        scrollToBottom();
        
        // TODO: Send message to server via API
        // sendMessageToServer(messageText);
    }
    
    // Create message element
    function createMessageElement(data) {
        const messageItem = document.createElement('div');
        messageItem.className = `designer-message-item designer-message-from-${data.isFromUser ? 'user' : 'client'}`;
        
        const avatarWrapper = document.createElement('div');
        avatarWrapper.className = 'designer-message-avatar-wrapper';
        
        const avatar = document.createElement('div');
        avatar.className = `designer-message-avatar ${data.isFromUser ? 'designer-user-avatar' : ''}`;
        avatar.textContent = data.initials;
        avatarWrapper.appendChild(avatar);
        
        if (data.isFromUser) {
            const onlineStatus = document.createElement('span');
            onlineStatus.className = 'designer-message-status-online';
            avatarWrapper.appendChild(onlineStatus);
        }
        
        const messageContent = document.createElement('div');
        messageContent.className = 'designer-message-content';
        
        const messageHeader = document.createElement('div');
        messageHeader.className = 'designer-message-header';
        
        const sender = document.createElement('div');
        sender.className = 'designer-message-sender';
        sender.textContent = data.sender;
        messageHeader.appendChild(sender);
        
        const time = document.createElement('span');
        time.className = 'designer-message-time';
        time.textContent = data.time;
        messageHeader.appendChild(time);
        
        messageContent.appendChild(messageHeader);
        
        // Message text - always show full message
        const textWrapper = document.createElement('div');
        textWrapper.className = 'designer-message-text-wrapper';
        
        const messageText = document.createElement('p');
        messageText.className = 'designer-message-text';
        messageText.textContent = data.text;
        
        textWrapper.appendChild(messageText);
        messageContent.appendChild(textWrapper);
        
        messageItem.appendChild(avatarWrapper);
        messageItem.appendChild(messageContent);
        
        return messageItem;
    }
    
    
    // Get current time formatted
    function getCurrentTime() {
        const now = new Date();
        const hours = now.getHours();
        const minutes = now.getMinutes();
        const ampm = hours >= 12 ? 'PM' : 'AM';
        const displayHours = hours % 12 || 12;
        const displayMinutes = minutes < 10 ? '0' + minutes : minutes;
        return `${displayHours}:${displayMinutes} ${ampm}`;
    }
    
    // Scroll to bottom of chat
    function scrollToBottom() {
        if (chatMessages) {
            chatMessages.scrollTop = chatMessages.scrollHeight;
        }
    }
    
    // Send button click handler
    if (chatSendBtn) {
        chatSendBtn.addEventListener('click', function(e) {
            e.preventDefault();
            sendMessage();
        });
    }
    
    // Conversation item click handler
    conversationItems.forEach(function(item) {
        item.addEventListener('click', function() {
            // Remove active class from all items
            conversationItems.forEach(function(convItem) {
                convItem.classList.remove('active');
            });
            
            // Add active class to clicked item
            this.classList.add('active');
            
            // TODO: Load conversation messages
            // loadConversation(this.dataset.conversationId);
        });
    });
    
    // Auto-scroll on page load
    if (chatMessages) {
        setTimeout(scrollToBottom, 100);
    }
    
    // Remove any existing expand toggles from messages
    function initExistingMessages() {
        const existingMessages = document.querySelectorAll('.designer-message-text-wrapper');
        existingMessages.forEach(function(wrapper) {
            const existingToggle = wrapper.querySelector('.designer-message-expand-toggle');
            if (existingToggle) {
                existingToggle.remove();
            }
            // Ensure message text is always fully visible
            const messageText = wrapper.querySelector('.designer-message-text');
            if (messageText) {
                messageText.classList.add('expanded');
            }
        });
    }
    
    // Initialize existing messages on page load
    initExistingMessages();
    
    // Designer info section toggle
    const designerInfoSectionHeaders = document.querySelectorAll('.designer-info-section-header');
    designerInfoSectionHeaders.forEach(function(header) {
        header.addEventListener('click', function() {
            const isExpanded = this.getAttribute('aria-expanded') === 'true';
            this.setAttribute('aria-expanded', !isExpanded);
            
            const content = this.nextElementSibling;
            if (content && content.classList.contains('designer-info-section-content')) {
                if (isExpanded) {
                    content.style.display = 'none';
                } else {
                    content.style.display = 'flex';
                }
            }
        });
        
        // Initialize state
        const content = header.nextElementSibling;
        if (content && content.classList.contains('designer-info-section-content')) {
            const isInitiallyExpanded = header.getAttribute('aria-expanded') !== 'false';
            header.setAttribute('aria-expanded', isInitiallyExpanded);
            if (!isInitiallyExpanded) {
                content.style.display = 'none';
            }
        }
    });
    
    // Toggle designer info sidebar functionality
    const toggleDesignerInfoBtn = document.getElementById('toggleDesignerInfo');
    const designerInfoClose = document.querySelector('.designer-info-close');
    const designerInfoSidebar = document.getElementById('designerInfoSidebar');
    const messagesContainer = document.querySelector('.designer-messages-container');
    
    if (designerInfoSidebar && messagesContainer) {
        // Function to update grid layout based on sidebar visibility
        function updateGridLayout(isHidden) {
            if (isHidden) {
                messagesContainer.style.gridTemplateColumns = '320px 1fr';
            } else {
                // Reset to default (will be responsive based on screen size)
                const width = window.innerWidth;
                if (width <= 1200 && width > 1024) {
                    messagesContainer.style.gridTemplateColumns = '280px 1fr 280px';
                } else if (width > 1200) {
                    messagesContainer.style.gridTemplateColumns = '320px 1fr 320px';
                } else {
                    // Mobile already handled by CSS
                    messagesContainer.style.gridTemplateColumns = '';
                }
            }
        }
        
        // Function to hide sidebar with animation
        function hideSidebar() {
            if (designerInfoSidebar.classList.contains('hidden')) return;
            
            // Add closing class for animation
            designerInfoSidebar.classList.add('closing');
            
            // After animation completes, hide it
            setTimeout(function() {
                designerInfoSidebar.classList.add('hidden');
                designerInfoSidebar.classList.remove('closing');
                updateGridLayout(true);
                localStorage.setItem('designerInfoHidden', 'true');
                if (toggleDesignerInfoBtn) {
                    updateToggleButtonIcon(toggleDesignerInfoBtn, false);
                }
            }, 300); // Match animation duration
        }
        
        // Function to show sidebar with animation
        function showSidebar() {
            if (!designerInfoSidebar.classList.contains('hidden')) return;
            
            // Remove hidden class first
            designerInfoSidebar.classList.remove('hidden');
            
            // Trigger reflow to ensure animation starts
            designerInfoSidebar.offsetHeight;
            
            // Add opening class for animation
            designerInfoSidebar.classList.add('opening');
            
            // Remove opening class after animation
            setTimeout(function() {
                designerInfoSidebar.classList.remove('opening');
            }, 300); // Match animation duration
            
            updateGridLayout(false);
            localStorage.setItem('designerInfoHidden', 'false');
            if (toggleDesignerInfoBtn) {
                updateToggleButtonIcon(toggleDesignerInfoBtn, true);
            }
        }
        
        // Check initial state from localStorage or default to visible
        const isInitiallyHidden = localStorage.getItem('designerInfoHidden') === 'true';
        if (isInitiallyHidden) {
            designerInfoSidebar.classList.add('hidden');
            updateGridLayout(true);
            if (toggleDesignerInfoBtn) {
                updateToggleButtonIcon(toggleDesignerInfoBtn, false);
            }
        } else {
            updateGridLayout(false);
            if (toggleDesignerInfoBtn) {
                updateToggleButtonIcon(toggleDesignerInfoBtn, true);
            }
        }
        
        // Toggle button click handler
        if (toggleDesignerInfoBtn) {
            toggleDesignerInfoBtn.addEventListener('click', function(e) {
                e.preventDefault();
                const isCurrentlyVisible = !designerInfoSidebar.classList.contains('hidden');
                
                if (isCurrentlyVisible) {
                    hideSidebar();
                } else {
                    showSidebar();
                }
            });
        }
        
        // Close button click handler
        if (designerInfoClose) {
            designerInfoClose.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                // Check if on mobile (different behavior)
                const isMobile = window.innerWidth <= 1024;
                if (isMobile) {
                    const designerInfo = document.querySelector('.designer-messages-designer-info');
                    if (designerInfo) {
                        designerInfo.classList.remove('mobile-open');
                    }
                } else {
                    hideSidebar();
                }
            });
        }
        
        // Handle window resize to maintain proper layout
        let resizeTimeout;
        window.addEventListener('resize', function() {
            clearTimeout(resizeTimeout);
            resizeTimeout = setTimeout(function() {
                if (!designerInfoSidebar.classList.contains('hidden')) {
                    updateGridLayout(false);
                }
            }, 100);
        });
    }
    
    // Update toggle button icon based on state
    function updateToggleButtonIcon(button, isVisible) {
        const icon = button.querySelector('i');
        if (icon) {
            if (isVisible) {
                icon.className = 'bi bi-arrow-right';
                button.setAttribute('title', 'Hide profile info');
            } else {
                icon.className = 'bi bi-arrow-left';
                button.setAttribute('title', 'Show profile info');
            }
        }
    }
}

// Designer Studio Homepage Functionality
function initDesignerStudio() {
    // Spotlight navigation
    const spotlightNavBtns = document.querySelectorAll('.spotlight-nav-btn');
    const spotlightSlides = document.querySelectorAll('.spotlight-slide');
    
    spotlightNavBtns.forEach(function(btn) {
        btn.addEventListener('click', function() {
            const targetSlide = this.getAttribute('data-slide');
            
            // Remove active class from all buttons and slides
            spotlightNavBtns.forEach(function(b) {
                b.classList.remove('active');
            });
            spotlightSlides.forEach(function(s) {
                s.classList.remove('active');
            });
            
            // Add active class to clicked button and corresponding slide
            this.classList.add('active');
            const targetSlideEl = document.querySelector('.spotlight-slide[data-slide="' + targetSlide + '"]');
            if (targetSlideEl) {
                targetSlideEl.classList.add('active');
            }
        });
    });
    
    
    // Moodboard pin functionality
    const moodboardPinBtns = document.querySelectorAll('.moodboard-pin-btn');
    
    moodboardPinBtns.forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const icon = this.querySelector('i');
            if (icon) {
                // Toggle pinned state
                const isPinned = icon.classList.contains('bi-pin-angle-fill');
                if (isPinned) {
                    icon.className = 'bi bi-pin-angle';
                    this.style.background = 'rgba(255, 255, 255, 0.9)';
                } else {
                    icon.className = 'bi bi-pin-angle-fill';
                    this.style.background = 'var(--color-primary)';
                    this.style.color = '#ffffff';
                }
                
                // TODO: Save pinned state to backend
                console.log('Moodboard item pinned/unpinned');
            }
        });
    });
    
    // Hero CTA button
    const heroCtaBtn = document.querySelector('.studio-hero-cta');
    if (heroCtaBtn) {
        heroCtaBtn.addEventListener('click', function(e) {
            e.preventDefault();
            // TODO: Navigate to latest project or implement project selection
            console.log('Resume latest project clicked');
        });
    }
}

// Initialize Designer Studio on page load
document.addEventListener('DOMContentLoaded', function() {
    initDesignerStudio();
    initHeroPattern();
});

// Randomly assign one of 5 arrow patterns to hero ambient background
function initHeroPattern() {
    const ambientElement = document.getElementById('heroAmbientPattern');
    if (!ambientElement) return;
    
    // Generate random number between 1 and 5
    const randomPattern = Math.floor(Math.random() * 5) + 1;
    
    // Remove any existing pattern classes
    ambientElement.classList.remove('pattern-1', 'pattern-2', 'pattern-3', 'pattern-4', 'pattern-5');
    
    // Add the randomly selected pattern class
    ambientElement.classList.add(`pattern-${randomPattern}`);
    
    console.log(`Hero pattern ${randomPattern} selected`);
}
