/**
 * LearnPress Next Button Controller
 *
 * Controls Next button visibility based on lesson completion status.
 *
 * Logic:
 * - Show Next if current lesson is completed
 * - Hide Next if current lesson is not completed
 */

(function() {
    'use strict';

    let removedNextButton = null;
    let nextButtonParent = null;
    let nextButtonNextSibling = null;

    /**
     * Check if lesson is completed
     */
    function isLessonCompleted() {
        const completedButton = document.querySelector('button.lp-button.completed');
        if (completedButton) {
            const successMessage = document.querySelector('.learn-press-message.success');
            return successMessage !== null;
        }
        return false;
    }


    /**
     * Remove retake count from button text like "(942)"
     */
    function removeRetakeCountFromButtons() {
        const buttons = document.querySelectorAll('button, a.button, .lp-button');
        buttons.forEach(function(button) {
            const text = button.textContent || button.innerText;
            if (/\(\s*\d+\s*\)/.test(text)) {
                const cleanText = text.replace(/\s*\(\s*\d+\s*\)\s*/g, '').trim();
                if (button.childNodes.length === 1 && button.childNodes[0].nodeType === Node.TEXT_NODE) {
                    button.textContent = cleanText;
                } else {
                    button.childNodes.forEach(function(node) {
                        if (node.nodeType === Node.TEXT_NODE) {
                            node.textContent = node.textContent.replace(/\s*\(\s*\d+\s*\)\s*/g, '').trim();
                        }
                    });
                }
            }
        });
    }

    /**
     * Find the Next button
     */
    function findNextButton() {
        return document.querySelector('.thim-ekit-single-course-item__navigation__next') ||
               document.querySelector('a[rel="next"]');
    }

    /**
     * Remove success message
     */
    function removeSuccessMessage() {
        const msg = document.querySelector('.learn-press-message.success.lp-content-area');
        if (msg) msg.remove();
    }

    /**
     * Toggle Next button visibility based on lesson completion
     */
    function toggleNextButton() {
        const lessonCompleted = isLessonCompleted();

        if (lessonCompleted) {
            // Show Next button
            if (removedNextButton && nextButtonParent) {
                if (nextButtonNextSibling) {
                    nextButtonParent.insertBefore(removedNextButton, nextButtonNextSibling);
                } else {
                    nextButtonParent.appendChild(removedNextButton);
                }
                removedNextButton = null;
                nextButtonParent = null;
                nextButtonNextSibling = null;
            }
            removeSuccessMessage();
        } else {
            // Hide Next button
            const nextButton = findNextButton();
            if (nextButton && nextButton.parentNode) {
                removedNextButton = nextButton;
                nextButtonParent = nextButton.parentNode;
                nextButtonNextSibling = nextButton.nextSibling;
                nextButton.remove();
            }
        }
    }

    /**
     * Watch for DOM changes
     */
    function setupMutationObserver() {
        const observer = new MutationObserver(function(mutations) {
            for (let mutation of mutations) {
                if (mutation.type === 'attributes' || mutation.type === 'childList') {
                    const target = mutation.target;
                    if (target.classList && (
                        target.classList.contains('lp-button') ||
                        target.classList.contains('learn-press-message') ||
                        target.classList.contains('thim-ekit-single-item__data')
                    )) {
                        removeRetakeCountFromButtons();
                        toggleNextButton();
                    }
                }
            }
        });

        observer.observe(document.body, {
            childList: true,
            subtree: true,
            attributes: true,
            attributeFilter: ['class']
        });
    }

    /**
     * Initialize
     */
    function init() {
        removeSuccessMessage();
        removeRetakeCountFromButtons();
        toggleNextButton();
        setupMutationObserver();

        // Check after delays for dynamic content
        setTimeout(function() {
            removeSuccessMessage();
            removeRetakeCountFromButtons();
            toggleNextButton();
        }, 500);

        // Listen for lesson completion
        document.addEventListener('learn-press/lesson-completed', function() {
            setTimeout(function() {
                removeSuccessMessage();
                removeRetakeCountFromButtons();
                toggleNextButton();
            }, 100);
        });

        // Listen for form submit
        const form = document.querySelector('form[name="learn-press-form-complete-lesson"]');
        if (form) {
            form.addEventListener('submit', function() {
                setTimeout(function() {
                    removeSuccessMessage();
                    removeRetakeCountFromButtons();
                    toggleNextButton();
                }, 1000);
            });
        }
    }

    // Run
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();
