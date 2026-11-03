/**
 * Helps customers fill in the MRZ blanks: upper case, a space becomes "<",
 * the cursor moves on when a blank is full, and a pasted MRZ is spread over
 * the blanks. Plain JavaScript, no framework.
 */
(function () {
    'use strict';

    var clean = function (text) {
        return text.toUpperCase().replace(/ /g, '<').replace(/[^A-Z0-9<]/g, '');
    };

    var blankLength = function (input) {
        return parseInt(input.dataset.mrzLength || input.maxLength, 10);
    };

    /**
     * Spreads pasted text over the blanks of a card: the whole MRZ, a whole line, or the start of a line that is
     * longer than the blank it was pasted into. Returns false to leave the paste to the browser.
     */
    var spread = function (card, target, pasted) {
        var lines = pasted.split(/\r?\n/).map(clean).filter(function (line) {
            return line !== '';
        });
        var lineCount = parseInt(card.dataset.mrzLineCount, 10);
        var lineLength = parseInt(card.dataset.mrzLineLength, 10);
        var placed = {};

        if (lines.length === lineCount) {
            lines.forEach(function (line, number) {
                placed[number] = {text: line, from: 0};
            });
        } else if (lines.length === 1 && lines[0].length === lineLength) {
            placed[target.dataset.mrzLine] = {text: lines[0], from: 0};
        } else if (lines.length === 1 && lines[0].length > blankLength(target)) {
            var from = parseInt(target.dataset.mrzStart, 10);
            if (from + lines[0].length > lineLength) {
                return false;
            }
            placed[target.dataset.mrzLine] = {text: lines[0], from: from};
        } else {
            return false;
        }

        card.querySelectorAll('input.mrz-segment').forEach(function (input) {
            var part = placed[input.dataset.mrzLine];
            if (part === undefined) {
                return;
            }
            var start = parseInt(input.dataset.mrzStart, 10);
            var end = start + blankLength(input);
            if (part.from >= end || part.from + part.text.length <= start) {
                return;
            }
            var value = part.text.slice(Math.max(0, start - part.from), end - part.from);
            if (part.from > start) {
                value = input.value.slice(0, part.from - start).padEnd(part.from - start, '<') + value;
            }
            if (input.classList.contains('mrz-segment--filler')) {
                value = value.padEnd(blankLength(input), '<');
            }
            input.value = value;
        });

        return true;
    };

    var markData = function (input) {
        if (input.classList.contains('mrz-segment--filler')) {
            input.classList.toggle('mrz-segment--data', /[^<]/.test(input.value));
        }
    };

    var initCard = function (card) {
        var inputs = Array.prototype.slice.call(card.querySelectorAll('input.mrz-segment'));

        inputs.forEach(function (input, position) {
            var filler = input.classList.contains('mrz-segment--filler');
            var length = input.maxLength;
            input.dataset.mrzLength = length;
            // A filler blank is always full, so the browser would refuse new characters (it overwrites instead).
            if (filler) {
                input.removeAttribute('maxlength');
            }

            input.addEventListener('input', function (event) {
                var cleaned = clean(input.value);
                var caret;
                if (cleaned !== input.value) {
                    caret = input.selectionStart - (input.value.length - cleaned.length);
                    input.value = cleaned;
                    input.setSelectionRange(caret, caret);
                }
                if (filler) {
                    caret = input.selectionStart;
                    if (input.value.length > length) {
                        var extra = input.value.length - length;
                        input.value = (input.value.slice(0, caret) + input.value.slice(caret + extra)).slice(0, length);
                        input.setSelectionRange(caret, caret);
                    } else if (input.value.length < length) {
                        input.value = input.value.slice(0, caret) + '<'.repeat(length - input.value.length) + input.value.slice(caret);
                        input.setSelectionRange(caret, caret);
                    }
                }
                markData(input);
                var next = inputs[position + 1];
                if (next && event.inputType === 'insertText' && input.selectionStart >= length && input.selectionEnd >= length) {
                    next.focus();
                    next.setSelectionRange(0, 0);
                }
            });

            input.addEventListener('keydown', function (event) {
                var previous = inputs[position - 1];
                var next = inputs[position + 1];
                var atStart = input.selectionStart === 0 && input.selectionEnd === 0;
                var atEnd = input.selectionStart === input.value.length && input.selectionEnd === input.value.length;
                if ((event.key === 'Backspace' || event.key === 'ArrowLeft') && previous && atStart) {
                    event.preventDefault();
                    previous.focus();
                    previous.setSelectionRange(previous.value.length, previous.value.length);
                } else if (event.key === 'ArrowRight' && next && atEnd) {
                    event.preventDefault();
                    next.focus();
                    next.setSelectionRange(0, 0);
                }
            });

            input.addEventListener('paste', function (event) {
                var pasted = (event.clipboardData || window.clipboardData).getData('text');
                if (spread(card, input, pasted)) {
                    event.preventDefault();
                    inputs.forEach(markData);
                }
            });

            markData(input);
        });
    };

    var init = function () {
        document.querySelectorAll('[data-mrz-form]').forEach(function (form) {
            var cards = Array.prototype.slice.call(form.querySelectorAll('[data-mrz-card]'));
            var select = form.querySelector('[data-mrz-type]');
            cards.forEach(initCard);
            // The error of the last attempt no longer applies once a new code is typed.
            var error = document.querySelector('[data-mrz-error]');
            if (error) {
                form.addEventListener('input', function () {
                    error.hidden = true;
                }, {once: true});
            }
            if (select) {
                select.addEventListener('change', function () {
                    cards.forEach(function (card) {
                        card.hidden = card.dataset.mrzCard !== select.value;
                    });
                });
            }
        });
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
}());
