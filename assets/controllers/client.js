// Client header dropdown functionality
function toggleDropdown() {
    const dropdown = document.getElementById('clientDropdown');
    if (dropdown) {
        dropdown.classList.toggle('show');
    }
}

// Close dropdown when clicking outside
document.addEventListener('click', function(event) {
    const dropdown = document.getElementById('clientDropdown');
    const badge = document.querySelector('.client-badge');
    
    if (dropdown && badge && !badge.contains(event.target) && !dropdown.contains(event.target)) {
        dropdown.classList.remove('show');
    }
});

// Search functionality for client search
document.addEventListener('DOMContentLoaded', function() {
    // Edit Photo Modal logic
    const openBtn = document.getElementById('openEditPhoto');
    const modal = document.getElementById('editPhotoModal');
    const closeBtn = document.getElementById('closeEditPhoto');
    const backdrop = modal ? modal.querySelector('.client-photo-modal__backdrop') : null;
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
    const thumbImgs = document.querySelectorAll('.client-photo-thumb img');
    const cropperEl = document.querySelector('.client-photo-cropper');
    const moveBadge = document.querySelector('.client-photo-move-badge');

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
        const cropper = previewImg.closest('.client-photo-cropper');
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
            const container = img.closest('.client-photo-thumb');
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
                const container = img.closest('.client-photo-thumb');
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
                const cropper = previewImg.closest('.client-photo-cropper');
                if (cropper) {
                    cropper.classList.remove('client-photo-cropper--empty');
                    const hint = cropper.querySelector('.client-photo-drop-hint');
                    if (hint) hint.remove();
                    // ensure Move badge exists and is visible (shown when cursor not inside)
                    if (!cropper.querySelector('.client-photo-move-badge')) {
                        const badge = document.createElement('div');
                        badge.className = 'client-photo-move-badge';
                        badge.innerHTML = '<i class="bi bi-arrows-move"></i>Move';
                        cropper.appendChild(badge);
                    }
                    const badgeEl = cropper.querySelector('.client-photo-move-badge');
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

                const response = await fetch('/client/profile/upload-photo', {
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
    const searchInput = document.getElementById('clientSearchInput');
    
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            const query = this.value.trim();
            
            // TODO: Implement client-specific search functionality
            // This could search orders, designers, services, etc.
            console.log('Client search query:', query);
        });
    }

    // Edit Bio Modal logic
    const openBioBtn = document.getElementById('openEditBio');
    const bioModal = document.getElementById('editBioModal');
    const closeBioBtn = document.getElementById('closeEditBio');
    const bioBackdrop = bioModal ? bioModal.querySelector('.client-bio-modal__backdrop') : null;
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
                
                const response = await fetch('/client/profile/update-bio', {
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

// Notification bell click handler
document.addEventListener('DOMContentLoaded', function() {
    const bellButton = document.querySelector('.client-icon.bell');
    
    if (bellButton) {
        bellButton.addEventListener('click', function() {
            // TODO: Implement notification functionality
            console.log('Notification bell clicked');
        });
    }

    // Projects Page Functionality
    initProjectsPage();
    
    // Messages Page Functionality
    initMessagesPage();
});

// Projects Page Initialization
function initProjectsPage() {
    // Tab Switching
    const tabButtons = document.querySelectorAll('.tab-btn');
    const tabContents = document.querySelectorAll('.tab-content');
    
    tabButtons.forEach(function(button) {
        button.addEventListener('click', function() {
            const targetTab = this.getAttribute('data-tab');
            
            // Remove active class from all tabs and contents
            tabButtons.forEach(function(btn) {
                btn.classList.remove('active');
            });
            tabContents.forEach(function(content) {
                content.classList.remove('active');
            });
            
            // Add active class to clicked tab and corresponding content
            this.classList.add('active');
            // Map tab names to content IDs
            const tabMap = {
                'my-projects': 'contentMyProjects',
                'explore': 'contentExplore'
            };
            const targetContent = document.getElementById(tabMap[targetTab]);
            if (targetContent) {
                targetContent.classList.add('active');
            }
        });
    });

    // Post Project Modal
    const postProjectBtn = document.getElementById('postProjectBtn');
    const postProjectEmptyBtn = document.getElementById('postProjectEmptyBtn');
    const postProjectModal = document.getElementById('postProjectModal');
    const closePostProjectBtn = document.getElementById('closePostProject');
    const cancelPostProjectBtn = document.getElementById('cancelPostProject');
    const postProjectForm = document.getElementById('postProjectForm');
    const backdrop = postProjectModal ? postProjectModal.querySelector('.project-modal__backdrop') : null;

    function openPostProjectModal() {
        if (postProjectModal) {
            postProjectModal.classList.remove('hidden');
            postProjectModal.classList.remove('closing');
            // Focus first input
            const firstInput = postProjectForm ? postProjectForm.querySelector('input, textarea, select') : null;
            if (firstInput) {
                setTimeout(function() {
                    firstInput.focus();
                }, 100);
            }
        }
    }

    function closePostProjectModal() {
        if (postProjectModal && !postProjectModal.classList.contains('closing')) {
            postProjectModal.classList.add('closing');
            setTimeout(function() {
                if (postProjectModal) {
                    postProjectModal.classList.add('hidden');
                    postProjectModal.classList.remove('closing');
                    // Reset form
                    if (postProjectForm) {
                        postProjectForm.reset();
                    }
                }
            }, 250);
        }
    }

    if (postProjectBtn) {
        postProjectBtn.addEventListener('click', function(e) {
            e.preventDefault();
            openPostProjectModal();
        });
    }

    if (postProjectEmptyBtn) {
        postProjectEmptyBtn.addEventListener('click', function(e) {
            e.preventDefault();
            openPostProjectModal();
        });
    }

    if (closePostProjectBtn) {
        closePostProjectBtn.addEventListener('click', closePostProjectModal);
    }

    if (cancelPostProjectBtn) {
        cancelPostProjectBtn.addEventListener('click', closePostProjectModal);
    }

    if (backdrop) {
        backdrop.addEventListener('click', closePostProjectModal);
    }

    // Handle Escape key to close modal
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && postProjectModal && !postProjectModal.classList.contains('hidden')) {
            closePostProjectModal();
        }
    });

    // Form Submission
    if (postProjectForm) {
        postProjectForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const submitBtn = document.getElementById('submitPostProject');
            const originalText = submitBtn ? submitBtn.textContent : 'Post Project';
            
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.textContent = 'Posting...';
            }

            try {
                // Get form data
                const formData = new FormData(postProjectForm);
                const projectData = {
                    title: formData.get('title') || '',
                    category: formData.get('category') || '',
                    description: formData.get('description') || '',
                    budget: formData.get('budget') || '',
                    visibility: formData.get('visibility') || 'public'
                };

                // Clear previous errors
                if (postProjectForm) {
                    clearFormErrors(postProjectForm);
                }

                // Validate required fields (client-side)
                let hasErrors = false;
                if (!projectData.title.trim()) {
                    showFieldError('title', 'Project title is required.');
                    hasErrors = true;
                }
                if (!projectData.category) {
                    showFieldError('category', 'Please select a category.');
                    hasErrors = true;
                }
                if (!projectData.description.trim()) {
                    showFieldError('description', 'Project description is required.');
                    hasErrors = true;
                }
                if (!projectData.budget) {
                    showFieldError('budget', 'Please select a budget range.');
                    hasErrors = true;
                }

                if (hasErrors) {
                    if (submitBtn) {
                        submitBtn.disabled = false;
                        submitBtn.textContent = originalText;
                    }
                    showNotification('error', 'Please fill in all required fields.');
                    return;
                }

                // Send API request to create project
                const response = await fetch('/client/projects/create', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify(projectData)
                });

                const result = await response.json();

                if (result.success) {
                    // Show success message using a better notification
                    showNotification('success', 'Project posted successfully!');
                    
                    // Close modal
                    closePostProjectModal();
                    
                    // Reset form and clear validation errors
                    if (postProjectForm) {
                        postProjectForm.reset();
                        clearFormErrors(postProjectForm);
                    }
                    
                    // Refresh projects list (reload page for now, can be enhanced later with AJAX)
                    setTimeout(function() {
                        window.location.reload();
                    }, 1000);
                } else {
                    // Handle validation errors
                    clearFormErrors(postProjectForm);
                    
                    if (result.errors && Object.keys(result.errors).length > 0) {
                        // Display field-specific errors
                        displayFormErrors(postProjectForm, result.errors);
                        showNotification('error', 'Please fix the errors in the form.');
                    } else {
                        showNotification('error', result.message || 'An error occurred while posting your project.');
                    }
                    
                    if (submitBtn) {
                        submitBtn.disabled = false;
                        submitBtn.textContent = originalText;
                    }
                }
                
            } catch (error) {
                console.error('Error posting project:', error);
                showNotification('error', 'Network error. Please check your connection and try again.');
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.textContent = originalText;
                }
            }
        });
    }

    // Search Functionality
    const projectsSearch = document.getElementById('projectsSearch');
    if (projectsSearch) {
        let searchTimeout;
        projectsSearch.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            const query = this.value.trim().toLowerCase();
            
            searchTimeout = setTimeout(function() {
                filterProjects(query);
            }, 300);
        });
    }

    // Filter Functionality
    const categoryFilter = document.getElementById('categoryFilter');
    const statusFilter = document.getElementById('statusFilter');
    const budgetFilter = document.getElementById('budgetFilter');

    [categoryFilter, statusFilter, budgetFilter].forEach(function(filter) {
        if (filter) {
            filter.addEventListener('change', function() {
                filterProjects();
            });
        }
    });

    // Notification System (simple toast-like notifications)
    function showNotification(type, message) {
        // Remove existing notifications
        const existingNotifications = document.querySelectorAll('.client-notification');
        existingNotifications.forEach(function(notif) {
            notif.remove();
        });

        // Create notification element
        const notification = document.createElement('div');
        notification.className = 'client-notification client-notification-' + type;
        notification.innerHTML = '<span>' + message + '</span><button class="notification-close" onclick="this.parentElement.remove()">&times;</button>';
        
        // Add to page
        document.body.appendChild(notification);
        
        // Show notification
        setTimeout(function() {
            notification.classList.add('show');
        }, 10);
        
        // Auto-remove after 5 seconds
        setTimeout(function() {
            notification.classList.remove('show');
            setTimeout(function() {
                if (notification.parentElement) {
                    notification.remove();
                }
            }, 300);
        }, 5000);
    }

    // Form Error Display Functions
    function displayFormErrors(form, errors) {
        if (!form) return;
        
        Object.keys(errors).forEach(function(fieldName) {
            const field = form.querySelector('[name="' + fieldName + '"]');
            if (field) {
                // Add error class
                field.classList.add('error');
                
                // Create or update error message
                let errorElement = field.parentElement.querySelector('.field-error');
                if (!errorElement) {
                    errorElement = document.createElement('div');
                    errorElement.className = 'field-error';
                    field.parentElement.appendChild(errorElement);
                }
                errorElement.textContent = errors[fieldName];
            }
        });
    }

    function clearFormErrors(form) {
        if (!form) return;
        
        // Remove error classes
        const errorFields = form.querySelectorAll('.error');
        errorFields.forEach(function(field) {
            field.classList.remove('error');
        });
        
        // Remove error messages
        const errorMessages = form.querySelectorAll('.field-error');
        errorMessages.forEach(function(msg) {
            msg.remove();
        });
    }

    function showFieldError(fieldName, message) {
        const form = document.getElementById('postProjectForm');
        if (!form) return;
        
        const field = form.querySelector('[name="' + fieldName + '"]');
        if (field) {
            field.classList.add('error');
            
            let errorElement = field.parentElement.querySelector('.field-error');
            if (!errorElement) {
                errorElement = document.createElement('div');
                errorElement.className = 'field-error';
                field.parentElement.appendChild(errorElement);
            }
            errorElement.textContent = message;
        }
    }

    // Filter Projects Function (client-side filtering - can be enhanced with server-side)
    function filterProjects(searchQuery) {
        const activeTab = document.querySelector('.tab-btn.active');
        if (!activeTab) return;
        
        const tabName = activeTab.getAttribute('data-tab');
        const gridId = tabName === 'my-projects' ? 'myProjectsGrid' : 'exploreProjectsGrid';
        const emptyStateId = tabName === 'my-projects' ? 'emptyMyProjects' : 'emptyExplore';
        const grid = document.getElementById(gridId);
        const emptyState = document.getElementById(emptyStateId);
        
        if (!grid) return;
        
        const projects = grid.querySelectorAll('.project-card');
        const categoryValue = categoryFilter ? categoryFilter.value : '';
        const statusValue = statusFilter ? statusFilter.value : '';
        const budgetValue = budgetFilter ? budgetFilter.value : '';
        
        let visibleCount = 0;
        
        projects.forEach(function(project) {
            let isVisible = true;
            
            // Search filter
            if (searchQuery && searchQuery.length > 0) {
                const title = project.querySelector('h3') ? project.querySelector('h3').textContent.toLowerCase() : '';
                const description = project.querySelector('.project-description') ? project.querySelector('.project-description').textContent.toLowerCase() : '';
                const category = project.querySelector('.project-category') ? project.querySelector('.project-category').textContent.toLowerCase() : '';
                
                if (!title.includes(searchQuery) && !description.includes(searchQuery) && !category.includes(searchQuery)) {
                    isVisible = false;
                }
            }
            
            // Category filter
            if (categoryValue && isVisible) {
                const projectCategory = project.querySelector('.project-category') ? project.querySelector('.project-category').textContent.toLowerCase() : '';
                const categoryMap = {
                    'logo': 'logo',
                    'poster': 'poster',
                    'web': 'web design',
                    'branding': 'branding',
                    'print': 'print design'
                };
                if (categoryMap[categoryValue] && !projectCategory.includes(categoryMap[categoryValue])) {
                    isVisible = false;
                }
            }
            
            // Status filter (only for My Projects)
            if (statusValue && tabName === 'my-projects' && isVisible) {
                const statusBadge = project.querySelector('.status-badge');
                if (statusBadge) {
                    const badgeClasses = statusBadge.classList;
                    const statusMap = {
                        'active': 'status-active',
                        'completed': 'status-completed',
                        'draft': 'status-draft'
                    };
                    if (statusMap[statusValue] && !badgeClasses.contains(statusMap[statusValue])) {
                        isVisible = false;
                    }
                }
            }
            
            // Budget filter (simplified - would need actual budget data)
            // This is a placeholder - in real implementation, projects would have budget data
            
            if (isVisible) {
                project.style.display = '';
                visibleCount++;
            } else {
                project.style.display = 'none';
            }
        });
        
        // Show/hide empty state
        if (emptyState) {
            if (visibleCount === 0) {
                emptyState.classList.remove('hidden');
                if (grid) grid.style.display = 'none';
            } else {
                emptyState.classList.add('hidden');
                if (grid) grid.style.display = '';
            }
        }
    }

    // Initialize modals after a short delay to ensure DOM is ready
    setTimeout(function() {
        initViewProjectModal();
        initEditProjectModal();
    }, 100);

    // Delete Project Modal
    initDeleteProjectModal();

    // Save/Share buttons in Explore tab
    const saveButtons = document.querySelectorAll('.icon-btn-small');
    saveButtons.forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const icon = this.querySelector('i');
            if (icon && icon.classList.contains('bi-bookmark')) {
                // Toggle save state
                if (icon.classList.contains('bi-bookmark-fill')) {
                    icon.classList.remove('bi-bookmark-fill');
                    icon.classList.add('bi-bookmark');
                    // TODO: Remove from saved projects
                } else {
                    icon.classList.remove('bi-bookmark');
                    icon.classList.add('bi-bookmark-fill');
                    // TODO: Add to saved projects
                }
            } else if (icon && icon.classList.contains('bi-share')) {
                // TODO: Implement share functionality
                console.log('Share project');
            }
        });
    });
}

// Messages Page Initialization
function initMessagesPage() {
    const chatInput = document.querySelector('.chat-input');
    const chatSendBtn = document.querySelector('.chat-send-btn');
    const chatMessages = document.querySelector('.chat-messages');
    const conversationItems = document.querySelectorAll('.conversation-item');
    
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
        messageItem.className = `message-item message-from-${data.isFromUser ? 'user' : 'client'}`;
        
        const avatarWrapper = document.createElement('div');
        avatarWrapper.className = 'message-avatar-wrapper';
        
        const avatar = document.createElement('div');
        avatar.className = `message-avatar ${data.isFromUser ? 'user-avatar' : ''}`;
        avatar.textContent = data.initials;
        avatarWrapper.appendChild(avatar);
        
        if (data.isFromUser) {
            const onlineStatus = document.createElement('span');
            onlineStatus.className = 'message-status-online';
            avatarWrapper.appendChild(onlineStatus);
        }
        
        const messageContent = document.createElement('div');
        messageContent.className = 'message-content';
        
        const messageHeader = document.createElement('div');
        messageHeader.className = 'message-header';
        
        const sender = document.createElement('div');
        sender.className = 'message-sender';
        sender.textContent = data.sender;
        messageHeader.appendChild(sender);
        
        const time = document.createElement('span');
        time.className = 'message-time';
        time.textContent = data.time;
        messageHeader.appendChild(time);
        
        messageContent.appendChild(messageHeader);
        
        // Message text - always show full message
        const textWrapper = document.createElement('div');
        textWrapper.className = 'message-text-wrapper';
        
        const messageText = document.createElement('p');
        messageText.className = 'message-text';
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
        const existingMessages = document.querySelectorAll('.message-text-wrapper');
        existingMessages.forEach(function(wrapper) {
            const existingToggle = wrapper.querySelector('.message-expand-toggle');
            if (existingToggle) {
                existingToggle.remove();
            }
            // Ensure message text is always fully visible
            const messageText = wrapper.querySelector('.message-text');
            if (messageText) {
                messageText.classList.add('expanded');
            }
        });
    }
    
    // Initialize existing messages on page load
    initExistingMessages();
    
    // Client info section toggle
    const clientInfoSectionHeaders = document.querySelectorAll('.client-info-section-header');
    clientInfoSectionHeaders.forEach(function(header) {
        header.addEventListener('click', function() {
            const isExpanded = this.getAttribute('aria-expanded') === 'true';
            this.setAttribute('aria-expanded', !isExpanded);
            
            const content = this.nextElementSibling;
            if (content && content.classList.contains('client-info-section-content')) {
                if (isExpanded) {
                    content.style.display = 'none';
                } else {
                    content.style.display = 'flex';
                }
            }
        });
        
        // Initialize state
        const content = header.nextElementSibling;
        if (content && content.classList.contains('client-info-section-content')) {
            const isInitiallyExpanded = header.getAttribute('aria-expanded') !== 'false';
            header.setAttribute('aria-expanded', isInitiallyExpanded);
            if (!isInitiallyExpanded) {
                content.style.display = 'none';
            }
        }
    });
    
    // Toggle client info sidebar functionality
    const toggleClientInfoBtn = document.getElementById('toggleClientInfo');
    const clientInfoClose = document.querySelector('.client-info-close');
    const clientInfoSidebar = document.getElementById('clientInfoSidebar');
    const messagesContainer = document.querySelector('.messages-container');
    
    if (clientInfoSidebar && messagesContainer) {
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
            if (clientInfoSidebar.classList.contains('hidden')) return;
            
            // Add closing class for animation
            clientInfoSidebar.classList.add('closing');
            
            // After animation completes, hide it
            setTimeout(function() {
                clientInfoSidebar.classList.add('hidden');
                clientInfoSidebar.classList.remove('closing');
                updateGridLayout(true);
                localStorage.setItem('clientInfoHidden', 'true');
                if (toggleClientInfoBtn) {
                    updateToggleButtonIcon(toggleClientInfoBtn, false);
                }
            }, 300); // Match animation duration
        }
        
        // Function to show sidebar with animation
        function showSidebar() {
            if (!clientInfoSidebar.classList.contains('hidden')) return;
            
            // Remove hidden class first
            clientInfoSidebar.classList.remove('hidden');
            
            // Trigger reflow to ensure animation starts
            clientInfoSidebar.offsetHeight;
            
            // Add opening class for animation
            clientInfoSidebar.classList.add('opening');
            
            // Remove opening class after animation
            setTimeout(function() {
                clientInfoSidebar.classList.remove('opening');
            }, 300); // Match animation duration
            
            updateGridLayout(false);
            localStorage.setItem('clientInfoHidden', 'false');
            if (toggleClientInfoBtn) {
                updateToggleButtonIcon(toggleClientInfoBtn, true);
            }
        }
        
        // Check initial state from localStorage or default to visible
        const isInitiallyHidden = localStorage.getItem('clientInfoHidden') === 'true';
        if (isInitiallyHidden) {
            clientInfoSidebar.classList.add('hidden');
            updateGridLayout(true);
            if (toggleClientInfoBtn) {
                updateToggleButtonIcon(toggleClientInfoBtn, false);
            }
        } else {
            updateGridLayout(false);
            if (toggleClientInfoBtn) {
                updateToggleButtonIcon(toggleClientInfoBtn, true);
            }
        }
        
        // Toggle button click handler
        if (toggleClientInfoBtn) {
            toggleClientInfoBtn.addEventListener('click', function(e) {
                e.preventDefault();
                const isCurrentlyVisible = !clientInfoSidebar.classList.contains('hidden');
                
                if (isCurrentlyVisible) {
                    hideSidebar();
                } else {
                    showSidebar();
                }
            });
        }
        
        // Close button click handler
        if (clientInfoClose) {
            clientInfoClose.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                // Check if on mobile (different behavior)
                const isMobile = window.innerWidth <= 1024;
                if (isMobile) {
                    const clientInfo = document.querySelector('.messages-client-info');
                    if (clientInfo) {
                        clientInfo.classList.remove('mobile-open');
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
                if (!clientInfoSidebar.classList.contains('hidden')) {
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

// View Project Modal
function initViewProjectModal() {
    const viewModal = document.getElementById('viewProjectModal');
    const closeBtn = document.getElementById('closeViewProject');
    const backdrop = viewModal ? viewModal.querySelector('.project-modal__backdrop') : null;
    const contentDiv = document.getElementById('viewProjectContent');
    const viewProjectBtns = document.querySelectorAll('.view-project-btn');

    if (!viewModal || !contentDiv) return;

    function openViewProjectModal(projectId) {
        if (!projectId) {
            console.error('Project ID is missing');
            return;
        }
        
        console.log('Opening view modal for project ID:', projectId);
        
        // Show loading state
        contentDiv.innerHTML = '<div style="text-align: center; padding: 3rem 2rem;"><div style="display: inline-block; width: 40px; height: 40px; border: 4px solid #f3f3f3; border-top: 4px solid var(--color-primary); border-radius: 50%; animation: spin 1s linear infinite;"></div><p style="margin-top: 1rem; font-family: \'Montserrat\', Arial, sans-serif; color: #6b7280; font-size: 14px;">Loading project details...</p></div>';
        
        // Show modal
        viewModal.classList.remove('hidden');
        viewModal.classList.remove('closing');
        viewModal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
        viewModal.style.display = 'flex';
        
        const url = `/client/projects/${projectId}?modal=1`;
        console.log('Fetching from URL:', url);
        
        // Fetch project details
        fetch(url, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => {
            console.log('Response status:', response.status, response.statusText);
            console.log('Response headers:', response.headers.get('content-type'));
            
            if (!response.ok) {
                // Try to get error message from JSON response
                return response.json().then(data => {
                    console.error('Error response:', data);
                    throw new Error(data.message || 'Failed to load project details');
                }).catch(() => {
                    console.error('Failed to parse error response as JSON');
                    throw new Error('Failed to load project details (Status: ' + response.status + ')');
                });
            }
            // Check if response is JSON (error) or HTML (success)
            const contentType = response.headers.get('content-type');
            if (contentType && contentType.includes('application/json')) {
                return response.json().then(data => {
                    console.error('Unexpected JSON response:', data);
                    throw new Error(data.message || 'Failed to load project details');
                });
            }
            return response.text();
        })
        .then(html => {
            console.log('Successfully loaded project details, HTML length:', html.length);
            contentDiv.innerHTML = html;
            
            // Re-attach event listener for edit button inside modal
            const editBtn = contentDiv.querySelector('.btn-edit-project-modal');
            if (editBtn) {
                editBtn.addEventListener('click', function() {
                    const projectId = this.getAttribute('data-project-id');
                    if (projectId) {
                        closeViewProjectModal();
                        setTimeout(() => openEditProjectModal(projectId), 300);
                    }
                });
            }
        })
        .catch(error => {
            console.error('Error loading project:', error);
            console.error('Error details:', {
                message: error.message,
                stack: error.stack
            });
            contentDiv.innerHTML = '<div style="text-align: center; padding: 3rem 2rem;"><div style="font-size: 3rem; color: #ef4444; margin-bottom: 1rem;"><i class="bi bi-exclamation-triangle"></i></div><p style="font-family: \'Montserrat\', Arial, sans-serif; color: #ef4444; font-size: 14px; margin-bottom: 0.5rem;">' + error.message + '</p><p style="font-family: \'Montserrat\', Arial, sans-serif; color: #6b7280; font-size: 13px;">Please check the browser console for more details.</p></div>';
        });
    }

    function closeViewProjectModal() {
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
    viewProjectBtns.forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const projectId = this.getAttribute('data-project-id');
            if (projectId) {
                openViewProjectModal(projectId);
            }
        });
    });

    if (closeBtn) {
        closeBtn.addEventListener('click', closeViewProjectModal);
    }

    if (backdrop) {
        backdrop.addEventListener('click', closeViewProjectModal);
    }

    // Handle Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && viewModal && !viewModal.classList.contains('hidden')) {
            closeViewProjectModal();
        }
    });

    // Make openViewProjectModal available globally for edit modal
    window.openViewProjectModal = openViewProjectModal;
    window.closeViewProjectModal = closeViewProjectModal;
}

// Edit Project Modal
function initEditProjectModal() {
    const editModal = document.getElementById('editProjectModal');
    const closeBtn = document.getElementById('closeEditProject');
    const backdrop = editModal ? editModal.querySelector('.project-modal__backdrop') : null;
    const contentDiv = document.getElementById('editProjectContent');
    const editProjectBtns = document.querySelectorAll('.edit-project-btn');

    if (!editModal || !contentDiv) return;

    function openEditProjectModal(projectId) {
        if (!projectId) return;
        
        // Show loading state
        contentDiv.innerHTML = '<div style="text-align: center; padding: 3rem 2rem;"><div style="display: inline-block; width: 40px; height: 40px; border: 4px solid #f3f3f3; border-top: 4px solid var(--color-primary); border-radius: 50%; animation: spin 1s linear infinite;"></div><p style="margin-top: 1rem; font-family: \'Montserrat\', Arial, sans-serif; color: #6b7280; font-size: 14px;">Loading project...</p></div>';
        
        // Show modal
        editModal.classList.remove('hidden');
        editModal.classList.remove('closing');
        editModal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
        editModal.style.display = 'flex';
        
        // Fetch project for editing
        fetch(`/client/projects/${projectId}/edit?modal=1`, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => {
            if (!response.ok) {
                // Try to get error message from JSON response
                return response.json().then(data => {
                    throw new Error(data.message || 'Failed to load project');
                }).catch(() => {
                    throw new Error('Failed to load project (Status: ' + response.status + ')');
                });
            }
            // Check if response is JSON (error) or HTML (success)
            const contentType = response.headers.get('content-type');
            if (contentType && contentType.includes('application/json')) {
                return response.json().then(data => {
                    throw new Error(data.message || 'Failed to load project');
                });
            }
            return response.text();
        })
        .then(html => {
            contentDiv.innerHTML = html;
            
            // Re-attach form submission handler
            const editForm = document.getElementById('editProjectForm');
            const editSubmitBtn = document.getElementById('submitEditProjectBtn');
            const cancelEditBtn = document.getElementById('cancelEditProject');
            
            if (editForm) {
                editForm.addEventListener('submit', handleEditProjectSubmit);
            }
            
            // Also attach click handler to submit button (since it's outside the form)
            if (editSubmitBtn) {
                editSubmitBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    if (editForm) {
                        // Trigger form validation
                        if (editForm.checkValidity()) {
                            handleEditProjectSubmit({ preventDefault: () => {}, target: editForm });
                        } else {
                            editForm.reportValidity();
                        }
                    }
                });
            }
            
            // Re-attach cancel button handler
            if (cancelEditBtn) {
                cancelEditBtn.addEventListener('click', closeEditProjectModal);
            }
            
            // Focus first input
            const firstInput = editForm ? editForm.querySelector('input, textarea, select') : null;
            if (firstInput) {
                setTimeout(() => firstInput.focus(), 100);
            }
        })
        .catch(error => {
            console.error('Error loading project:', error);
            contentDiv.innerHTML = '<div style="text-align: center; padding: 3rem 2rem;"><div style="font-size: 3rem; color: #ef4444; margin-bottom: 1rem;"><i class="bi bi-exclamation-triangle"></i></div><p style="font-family: \'Montserrat\', Arial, sans-serif; color: #ef4444; font-size: 14px; margin-bottom: 0.5rem;">' + error.message + '</p><p style="font-family: \'Montserrat\', Arial, sans-serif; color: #6b7280; font-size: 13px;">Please try again.</p></div>';
        });
    }

    function closeEditProjectModal() {
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

    function handleEditProjectSubmit(e) {
        e.preventDefault();
        const form = e.target;
        const submitBtn = document.getElementById('submitEditProjectBtn');
        const projectId = form.querySelector('#editProjectId') ? form.querySelector('#editProjectId').value : null;
        
        if (!projectId) {
            if (typeof showNotification === 'function') {
                showNotification('error', 'Project ID is missing.');
            } else {
                alert('Project ID is missing.');
            }
            return;
        }

        // Get form data
        const formData = {
            title: form.querySelector('#editProjectTitle') ? form.querySelector('#editProjectTitle').value : '',
            description: form.querySelector('#editProjectDescription') ? form.querySelector('#editProjectDescription').value : '',
            category: form.querySelector('#editProjectCategory') ? form.querySelector('#editProjectCategory').value : '',
            budget: form.querySelector('#editProjectBudget') ? form.querySelector('#editProjectBudget').value : ''
        };

        // Show loading state
        const originalText = submitBtn ? submitBtn.innerHTML : '';
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="bi bi-hourglass-split"></i> <span>Saving...</span>';
        }

        // Submit update
        fetch(`/client/projects/${projectId}/update`, {
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
                closeEditProjectModal();
                
                // Show success modal after a short delay
                setTimeout(function() {
                    showEditProjectSuccessModal();
                }, 300);
                
                // Refresh projects list after modal closes
                setTimeout(function() {
                    window.location.reload();
                }, 2000);
            } else {
                if (typeof showNotification === 'function') {
                    showNotification('error', result.message || 'Failed to update project.');
                } else {
                    alert(result.message || 'Failed to update project.');
                }
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalText;
                }
            }
        })
        .catch(error => {
            console.error('Error updating project:', error);
            if (typeof showNotification === 'function') {
                showNotification('error', 'Network error. Please check your connection and try again.');
            } else {
                alert('Network error. Please check your connection and try again.');
            }
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            }
        });
    }

    // Attach event listeners to edit buttons
    editProjectBtns.forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const projectId = this.getAttribute('data-project-id');
            if (projectId) {
                openEditProjectModal(projectId);
            }
        });
    });

    if (closeBtn) {
        closeBtn.addEventListener('click', closeEditProjectModal);
    }

    if (backdrop) {
        backdrop.addEventListener('click', closeEditProjectModal);
    }

    // Handle Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && editModal && !editModal.classList.contains('hidden')) {
            closeEditProjectModal();
        }
    });

    // Make openEditProjectModal available globally
    window.openEditProjectModal = openEditProjectModal;
}

// Delete Project Modal
function initDeleteProjectModal() {
    const deleteModal = document.getElementById('deleteProjectModal');
    const closeBtn = document.getElementById('closeDeleteProject');
    const backdrop = deleteModal ? deleteModal.querySelector('.project-modal__backdrop') : null;
    const contentDiv = document.getElementById('deleteProjectContent');
    const deleteButtons = document.querySelectorAll('.btn-danger[data-project-id]');

    if (!deleteModal || !contentDiv) return;

    function openDeleteProjectModal(projectId) {
        if (!projectId) {
            console.error('Project ID is missing');
            return;
        }
        
        console.log('Opening delete modal for project ID:', projectId);
        
        // Show loading state
        contentDiv.innerHTML = '<div style="text-align: center; padding: 3rem 2rem;"><div style="display: inline-block; width: 40px; height: 40px; border: 4px solid #f3f3f3; border-top: 4px solid var(--color-primary); border-radius: 50%; animation: spin 1s linear infinite;"></div><p style="margin-top: 1rem; font-family: \'Montserrat\', Arial, sans-serif; color: #6b7280; font-size: 14px;">Loading...</p></div>';
        
        // Show modal
        deleteModal.classList.remove('hidden');
        deleteModal.classList.remove('closing');
        deleteModal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
        deleteModal.style.display = 'flex';
        
        const url = `/client/projects/${projectId}/delete?modal=1`;
        console.log('Fetching from URL:', url);
        
        // Fetch delete confirmation modal
        fetch(url, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => {
            console.log('Response status:', response.status, response.statusText);
            
            if (!response.ok) {
                return response.json().then(data => {
                    throw new Error(data.message || 'Failed to load delete confirmation');
                }).catch(() => {
                    throw new Error('Failed to load delete confirmation (Status: ' + response.status + ')');
                });
            }
            const contentType = response.headers.get('content-type');
            if (contentType && contentType.includes('application/json')) {
                return response.json().then(data => {
                    throw new Error(data.message || 'Failed to load delete confirmation');
                });
            }
            return response.text();
        })
        .then(html => {
            console.log('Successfully loaded delete confirmation');
            contentDiv.innerHTML = html;
            
            // Re-attach event listeners
            const confirmBtn = contentDiv.querySelector('#confirmDeleteProject');
            const cancelBtn = contentDiv.querySelector('#cancelDeleteProject');
            
            if (confirmBtn) {
                confirmBtn.addEventListener('click', function() {
                    const projectId = this.getAttribute('data-project-id');
                    if (projectId) {
                        handleDeleteProject(projectId);
                    }
                });
            }
            
            if (cancelBtn) {
                cancelBtn.addEventListener('click', closeDeleteProjectModal);
            }
        })
        .catch(error => {
            console.error('Error loading delete confirmation:', error);
            contentDiv.innerHTML = '<div style="text-align: center; padding: 3rem 2rem;"><div style="font-size: 3rem; color: #ef4444; margin-bottom: 1rem;"><i class="bi bi-exclamation-triangle"></i></div><p style="font-family: \'Montserrat\', Arial, sans-serif; color: #ef4444; font-size: 14px; margin-bottom: 0.5rem;">' + error.message + '</p><p style="font-family: \'Montserrat\', Arial, sans-serif; color: #6b7280; font-size: 13px;">Please try again.</p></div>';
        });
    }

    function closeDeleteProjectModal() {
        if (deleteModal && !deleteModal.classList.contains('closing')) {
            deleteModal.classList.add('closing');
            deleteModal.setAttribute('aria-hidden', 'true');
            document.body.style.overflow = '';
            
            setTimeout(function() {
                if (deleteModal) {
                    deleteModal.classList.add('hidden');
                    deleteModal.classList.remove('closing');
                    deleteModal.style.display = '';
                    contentDiv.innerHTML = '';
                }
            }, 250);
        }
    }

    function handleDeleteProject(projectId) {
        const confirmBtn = document.getElementById('confirmDeleteProject');
        const originalText = confirmBtn ? confirmBtn.innerHTML : '';
        
        // Show loading state
        if (confirmBtn) {
            confirmBtn.disabled = true;
            confirmBtn.innerHTML = '<i class="bi bi-hourglass-split"></i> <span>Deleting...</span>';
        }
        
        // Delete the project
        fetch(`/client/projects/${projectId}/delete`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({})
        })
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                closeDeleteProjectModal();
                
                // Remove project card with animation first
                const projectCard = document.querySelector(`.project-card[data-project-id="${projectId}"]`);
                if (projectCard) {
                    projectCard.style.opacity = '0';
                    projectCard.style.transform = 'scale(0.95)';
                    setTimeout(function() {
                        if (projectCard && projectCard.parentNode) {
                            projectCard.parentNode.removeChild(projectCard);
                            // Check if we need to show empty state
                            const grid = projectCard.closest('.projects-grid');
                            if (grid && grid.querySelectorAll('.project-card').length === 0) {
                                const emptyState = document.getElementById('emptyMyProjects');
                                if (emptyState) {
                                    emptyState.classList.remove('hidden');
                                }
                            }
                        }
                    }, 300);
                }
                
                // Show success modal after a short delay
                setTimeout(function() {
                    showDeleteSuccessModal();
                }, 400);
            } else {
                if (typeof showNotification === 'function') {
                    showNotification('error', result.message || 'Failed to delete project.');
                } else {
                    alert(result.message || 'Failed to delete project.');
                }
                if (confirmBtn) {
                    confirmBtn.disabled = false;
                    confirmBtn.innerHTML = originalText;
                }
            }
        })
        .catch(error => {
            console.error('Error deleting project:', error);
            if (typeof showNotification === 'function') {
                showNotification('error', 'Network error. Please check your connection and try again.');
            } else {
                alert('Network error. Please check your connection and try again.');
            }
            if (confirmBtn) {
                confirmBtn.disabled = false;
                confirmBtn.innerHTML = originalText;
            }
        });
    }

    // Attach event listeners to delete buttons
    deleteButtons.forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const projectId = this.getAttribute('data-project-id');
            if (projectId) {
                openDeleteProjectModal(projectId);
            }
        });
    });

    if (closeBtn) {
        closeBtn.addEventListener('click', closeDeleteProjectModal);
    }

    if (backdrop) {
        backdrop.addEventListener('click', closeDeleteProjectModal);
    }

    // Handle Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && deleteModal && !deleteModal.classList.contains('hidden')) {
            closeDeleteProjectModal();
        }
    });

    // Make openDeleteProjectModal available globally
    window.openDeleteProjectModal = openDeleteProjectModal;
}

// Edit Project Success Modal
let editProjectSuccessModalTimeout = null;
let editProjectSuccessModalEscapeHandler = null;

function showEditProjectSuccessModal() {
    const successModal = document.getElementById('editProjectSuccessModal');
    const closeBtn = document.getElementById('closeEditProjectSuccessModal');
    const backdrop = successModal ? successModal.querySelector('.project-modal__backdrop') : null;
    
    if (!successModal) return;
    
    // Clear any existing timeout
    if (editProjectSuccessModalTimeout) {
        clearTimeout(editProjectSuccessModalTimeout);
        editProjectSuccessModalTimeout = null;
    }
    
    // Remove existing escape handler
    if (editProjectSuccessModalEscapeHandler) {
        document.removeEventListener('keydown', editProjectSuccessModalEscapeHandler);
        editProjectSuccessModalEscapeHandler = null;
    }
    
    function closeEditProjectSuccessModal() {
        if (successModal && !successModal.classList.contains('closing')) {
            successModal.classList.add('closing');
            successModal.setAttribute('aria-hidden', 'true');
            document.body.style.overflow = '';
            
            // Clear timeout
            if (editProjectSuccessModalTimeout) {
                clearTimeout(editProjectSuccessModalTimeout);
                editProjectSuccessModalTimeout = null;
            }
            
            // Remove escape handler
            if (editProjectSuccessModalEscapeHandler) {
                document.removeEventListener('keydown', editProjectSuccessModalEscapeHandler);
                editProjectSuccessModalEscapeHandler = null;
            }
            
            setTimeout(function() {
                if (successModal) {
                    successModal.classList.add('hidden');
                    successModal.classList.remove('closing');
                    successModal.style.display = '';
                }
            }, 250);
        }
    }
    
    // Show modal
    successModal.classList.remove('hidden');
    successModal.classList.remove('closing');
    successModal.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';
    successModal.style.display = 'flex';
    
    // Close button handler - use onclick to avoid duplicate listeners
    if (closeBtn) {
        closeBtn.onclick = closeEditProjectSuccessModal;
    }
    
    if (backdrop) {
        backdrop.onclick = closeEditProjectSuccessModal;
    }
    
    // Handle Escape key
    editProjectSuccessModalEscapeHandler = function(e) {
        if (e.key === 'Escape' && successModal && !successModal.classList.contains('hidden')) {
            closeEditProjectSuccessModal();
        }
    };
    document.addEventListener('keydown', editProjectSuccessModalEscapeHandler);
    
    // Auto-close after 3 seconds
    editProjectSuccessModalTimeout = setTimeout(function() {
        closeEditProjectSuccessModal();
    }, 3000);
}

// Delete Success Modal
let deleteSuccessModalTimeout = null;
let deleteSuccessModalEscapeHandler = null;

function showDeleteSuccessModal() {
    const successModal = document.getElementById('deleteSuccessModal');
    const closeBtn = document.getElementById('closeDeleteSuccessModal');
    const backdrop = successModal ? successModal.querySelector('.project-modal__backdrop') : null;
    
    if (!successModal) return;
    
    // Clear any existing timeout
    if (deleteSuccessModalTimeout) {
        clearTimeout(deleteSuccessModalTimeout);
        deleteSuccessModalTimeout = null;
    }
    
    // Remove existing escape handler
    if (deleteSuccessModalEscapeHandler) {
        document.removeEventListener('keydown', deleteSuccessModalEscapeHandler);
        deleteSuccessModalEscapeHandler = null;
    }
    
    function closeDeleteSuccessModal() {
        if (successModal && !successModal.classList.contains('closing')) {
            successModal.classList.add('closing');
            successModal.setAttribute('aria-hidden', 'true');
            document.body.style.overflow = '';
            
            // Clear timeout
            if (deleteSuccessModalTimeout) {
                clearTimeout(deleteSuccessModalTimeout);
                deleteSuccessModalTimeout = null;
            }
            
            // Remove escape handler
            if (deleteSuccessModalEscapeHandler) {
                document.removeEventListener('keydown', deleteSuccessModalEscapeHandler);
                deleteSuccessModalEscapeHandler = null;
            }
            
            setTimeout(function() {
                if (successModal) {
                    successModal.classList.add('hidden');
                    successModal.classList.remove('closing');
                    successModal.style.display = '';
                }
            }, 250);
        }
    }
    
    // Show modal
    successModal.classList.remove('hidden');
    successModal.classList.remove('closing');
    successModal.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';
    successModal.style.display = 'flex';
    
    // Close button handler - use onclick to avoid duplicate listeners
    if (closeBtn) {
        closeBtn.onclick = closeDeleteSuccessModal;
    }
    
    if (backdrop) {
        backdrop.onclick = closeDeleteSuccessModal;
    }
    
    // Handle Escape key
    deleteSuccessModalEscapeHandler = function(e) {
        if (e.key === 'Escape' && successModal && !successModal.classList.contains('hidden')) {
            closeDeleteSuccessModal();
        }
    };
    document.addEventListener('keydown', deleteSuccessModalEscapeHandler);
    
    // Auto-close after 3 seconds
    deleteSuccessModalTimeout = setTimeout(function() {
        closeDeleteSuccessModal();
    }, 3000);
}
