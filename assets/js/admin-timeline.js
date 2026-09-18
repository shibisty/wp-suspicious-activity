document.addEventListener('DOMContentLoaded', function () {
    // Toggle timeline
    document.querySelectorAll('.timeline-toggle').forEach(function (toggle) {
        toggle.addEventListener('click', function () {
            var content = this.parentElement.nextElementSibling;
            content.classList.toggle('active');
            this.textContent = content.classList.contains('active') ? 'Сховати ▲' : 'Показати ▼';
        });
    });

    // Timeline tooltip
    document.querySelectorAll('.timeline-segment').forEach(function (segment) {
        segment.addEventListener('mouseenter', function () {
            var tooltip = document.getElementById('timeline-tooltip');
            var data = this.dataset;
            tooltip.innerHTML = '<strong>' + data.devices + ' пристроїв</strong><br>' +
                               'Час: ' + data.time + '<br>' +
                               'Пристрої: ' + data.agent_list;
            tooltip.style.display = 'block';
        });
        segment.addEventListener('mousemove', function (e) {
            var tooltip = document.getElementById('timeline-tooltip');
            tooltip.style.left = (e.pageX + 10) + 'px';
            tooltip.style.top = (e.pageY + 10) + 'px';
        });
        segment.addEventListener('mouseleave', function () {
            document.getElementById('timeline-tooltip').style.display = 'none';
        });
    });

    // Interactive timeline (zoom/drag)
    document.querySelectorAll('.timeline-container').forEach(function (container) {
        var bar = container.querySelector('.timeline-bar');
        if (!bar) return;

        var rangeStart = parseInt(bar.dataset.rangeStart, 10);
        var rangeEnd = parseInt(bar.dataset.rangeEnd, 10);
        var totalSpan = rangeEnd - rangeStart;

        var state = {
            zoomLevel: 1,
            visibleStart: rangeStart,
            visibleEnd: rangeEnd,
            isDragging: false,
            dragStartX: 0,
            dragStartVisibleStart: 0
        };

        var zoomDisplay = container.querySelector('.timeline-zoom-level');
        var timeStartLabel = container.querySelector('.time-start');
        var timeEndLabel = container.querySelector('.time-end');

        function updateView() {
            var visibleSpan = state.visibleEnd - state.visibleStart;
            var zoomLevel = totalSpan / visibleSpan;
            state.zoomLevel = zoomLevel;

            var segments = bar.querySelectorAll('.timeline-segment, .timeline-gap');
            segments.forEach(function (segment) {
                var originalLeft = parseFloat(segment.dataset.originalLeft);
                var originalWidth = parseFloat(segment.dataset.originalWidth);

                var segmentStart = rangeStart + (originalLeft / 100) * totalSpan;
                var segmentEnd = segmentStart + (originalWidth / 100) * totalSpan;

                var newLeft = ((segmentStart - state.visibleStart) / visibleSpan) * 100;
                var newWidth = ((segmentEnd - segmentStart) / visibleSpan) * 100;

                segment.style.left = newLeft + '%';
                segment.style.width = newWidth + '%';
            });

            if (zoomDisplay) zoomDisplay.textContent = Math.round(zoomLevel * 100) + '%';
            if (timeStartLabel) timeStartLabel.textContent = formatTimestamp(state.visibleStart);
            if (timeEndLabel) timeEndLabel.textContent = formatTimestamp(state.visibleEnd);
        }

        function formatTimestamp(timestamp) {
            var date = new Date(timestamp * 1000);
            var visibleSpan = state.visibleEnd - state.visibleStart;

            var y = date.getFullYear();
            var m = String(date.getMonth() + 1).padStart(2, '0');
            var d = String(date.getDate()).padStart(2, '0');
            var h = String(date.getHours()).padStart(2, '0');
            var min = String(date.getMinutes()).padStart(2, '0');
            var s = String(date.getSeconds()).padStart(2, '0');

            if (visibleSpan < 3600) {
                return h + ':' + min + ':' + s;
            } else if (visibleSpan < 86400) {
                return h + ':' + min;
            } else if (visibleSpan < 604800) {
                return m + '-' + d + ' ' + h + ':' + min;
            }
            return y + '-' + m + '-' + d;
        }

        bar.addEventListener('wheel', function (e) {
            e.preventDefault();

            var rect = bar.getBoundingClientRect();
            var mouseX = e.clientX - rect.left;
            var mousePercent = mouseX / rect.width;

            var visibleSpan = state.visibleEnd - state.visibleStart;
            var timeAtMouse = state.visibleStart + mousePercent * visibleSpan;

            var zoomFactor = e.deltaY < 0 ? 1.5 : 1 / 1.5;
            var newVisibleSpan = visibleSpan / zoomFactor;

            var minVisibleSpan = totalSpan / 100;
            if (newVisibleSpan < minVisibleSpan) newVisibleSpan = minVisibleSpan;
            if (newVisibleSpan > totalSpan) newVisibleSpan = totalSpan;

            var newMousePercent = (timeAtMouse - state.visibleStart) / visibleSpan;
            state.visibleStart = timeAtMouse - newMousePercent * newVisibleSpan;
            state.visibleEnd = state.visibleStart + newVisibleSpan;

            if (state.visibleStart < rangeStart) {
                state.visibleStart = rangeStart;
                state.visibleEnd = rangeStart + newVisibleSpan;
            }
            if (state.visibleEnd > rangeEnd) {
                state.visibleEnd = rangeEnd;
                state.visibleStart = rangeEnd - newVisibleSpan;
            }

            updateView();
        }, { passive: false });

        bar.addEventListener('mousedown', function (e) {
            e.preventDefault();
            state.isDragging = true;
            state.dragStartX = e.clientX;
            state.dragStartVisibleStart = state.visibleStart;
            bar.style.cursor = 'grabbing';
            bar.style.userSelect = 'none';
        });

        document.addEventListener('mousemove', function (e) {
            if (!state.isDragging) return;

            var deltaX = e.clientX - state.dragStartX;
            var rect = bar.getBoundingClientRect();
            var visibleSpan = state.visibleEnd - state.visibleStart;
            var deltaPercent = deltaX / rect.width;
            var deltaTime = deltaPercent * visibleSpan;

            state.visibleStart = state.dragStartVisibleStart - deltaTime;
            state.visibleEnd = state.visibleStart + visibleSpan;

            if (state.visibleStart < rangeStart) {
                state.visibleStart = rangeStart;
                state.visibleEnd = state.visibleStart + visibleSpan;
            }
            if (state.visibleEnd > rangeEnd) {
                state.visibleEnd = rangeEnd;
                state.visibleStart = state.visibleEnd - visibleSpan;
            }

            updateView();
        });

        document.addEventListener('mouseup', function () {
            if (state.isDragging) {
                state.isDragging = false;
                bar.style.cursor = 'grab';
                bar.style.userSelect = '';
            }
        });

        var zoomInBtn = container.querySelector('.zoom-in');
        var zoomOutBtn = container.querySelector('.zoom-out');
        var resetZoomBtn = container.querySelector('.zoom-reset');

        if (zoomInBtn) {
            zoomInBtn.addEventListener('click', function () {
                var visibleSpan = state.visibleEnd - state.visibleStart;
                var centerTime = state.visibleStart + visibleSpan / 2;
                var newVisibleSpan = visibleSpan / 1.5;

                if (newVisibleSpan < totalSpan / 100) newVisibleSpan = totalSpan / 100;

                state.visibleStart = centerTime - newVisibleSpan / 2;
                state.visibleEnd = centerTime + newVisibleSpan / 2;

                if (state.visibleStart < rangeStart) {
                    state.visibleStart = rangeStart;
                    state.visibleEnd = rangeStart + newVisibleSpan;
                }
                if (state.visibleEnd > rangeEnd) {
                    state.visibleEnd = rangeEnd;
                    state.visibleStart = rangeEnd - newVisibleSpan;
                }

                updateView();
            });
        }

        if (zoomOutBtn) {
            zoomOutBtn.addEventListener('click', function () {
                var visibleSpan = state.visibleEnd - state.visibleStart;
                var centerTime = state.visibleStart + visibleSpan / 2;
                var newVisibleSpan = visibleSpan * 1.5;

                if (newVisibleSpan > totalSpan) newVisibleSpan = totalSpan;

                state.visibleStart = centerTime - newVisibleSpan / 2;
                state.visibleEnd = centerTime + newVisibleSpan / 2;

                if (state.visibleStart < rangeStart) {
                    state.visibleStart = rangeStart;
                    state.visibleEnd = rangeStart + newVisibleSpan;
                }
                if (state.visibleEnd > rangeEnd) {
                    state.visibleEnd = rangeEnd;
                    state.visibleStart = rangeEnd - newVisibleSpan;
                }

                updateView();
            });
        }

        if (resetZoomBtn) {
            resetZoomBtn.addEventListener('click', function () {
                state.visibleStart = rangeStart;
                state.visibleEnd = rangeEnd;
                updateView();
            });
        }

        updateView();
    });
});
