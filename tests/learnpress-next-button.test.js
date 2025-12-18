/**
 * Tests for LearnPress Next Button Control
 *
 * @jest-environment jsdom
 */

describe('LearnPress Next Button Control', () => {
	beforeEach(() => {
		// Setup DOM
		document.body.innerHTML = `
			<div class="thim-ekit-single-item__data">
				<button class="lp-button completed">Complete Lesson</button>
				<div class="learn-press-message success lp-content-area">
					Lesson completed successfully
				</div>
				<a class="thim-ekit-single-course-item__navigation__next" rel="next" href="/next-lesson">
					Next Lesson
				</a>
			</div>
			<button class="button-retake-course">Retake Course (942)</button>
		`;
	});

	describe('Lesson Completion Detection', () => {
		test('should detect completed lesson', () => {
			const completedButton = document.querySelector('button.lp-button.completed');
			const successMessage = document.querySelector('.learn-press-message.success');

			const isCompleted = completedButton !== null && successMessage !== null;

			expect(isCompleted).toBe(true);
		});

		test('should detect incomplete lesson', () => {
			// Remove success message
			document.querySelector('.learn-press-message.success')?.remove();

			const completedButton = document.querySelector('button.lp-button.completed');
			const successMessage = document.querySelector('.learn-press-message.success');

			const isCompleted = completedButton !== null && successMessage !== null;

			expect(isCompleted).toBe(false);
		});
	});

	describe('Retake Count Removal', () => {
		const removeRetakeCount = (text) => {
			return text.replace(/\s*\(\s*\d+\s*\)\s*/g, '').trim();
		};

		test('should remove retake count from button text', () => {
			const tests = [
				{ input: 'Retake Course (942)', expected: 'Retake Course' },
				{ input: 'Retake Course ( 942 )', expected: 'Retake Course' },
				{ input: 'Finish Course (123)', expected: 'Finish Course' },
				{ input: 'Complete Course  (  45  )', expected: 'Complete Course' },
				{ input: 'Continue Learning', expected: 'Continue Learning' }
			];

			tests.forEach(test => {
				expect(removeRetakeCount(test.input)).toBe(test.expected);
			});
		});

		test('should handle button HTML with retake count', () => {
			const button = document.querySelector('.button-retake-course');
			const originalText = button.textContent;

			expect(originalText).toBe('Retake Course (942)');

			// Simulate cleaning
			button.textContent = removeRetakeCount(originalText);

			expect(button.textContent).toBe('Retake Course');
		});

		test('should handle multiple retake count patterns', () => {
			const patterns = [
				'(123)',
				'( 456 )',
				'(  789  )',
				'( 1 )'
			];

			patterns.forEach(pattern => {
				const text = `Retake Course ${pattern}`;
				const cleaned = removeRetakeCount(text);
				expect(cleaned).toBe('Retake Course');
			});
		});
	});

	describe('Next Button Visibility', () => {
		test('should find next button', () => {
			const nextButton = document.querySelector('.thim-ekit-single-course-item__navigation__next') ||
			                   document.querySelector('a[rel="next"]');

			expect(nextButton).toBeTruthy();
			expect(nextButton.textContent.trim()).toBe('Next Lesson');
		});

		test('should hide next button when lesson incomplete', () => {
			const nextButton = document.querySelector('.thim-ekit-single-course-item__navigation__next');
			const parent = nextButton.parentNode;
			const nextSibling = nextButton.nextSibling;

			// Remove button (simulate hiding)
			nextButton.remove();

			expect(parent.querySelector('.thim-ekit-single-course-item__navigation__next')).toBeNull();

			// Restore button (simulate showing after completion)
			if (nextSibling) {
				parent.insertBefore(nextButton, nextSibling);
			} else {
				parent.appendChild(nextButton);
			}

			expect(parent.querySelector('.thim-ekit-single-course-item__navigation__next')).toBeTruthy();
		});
	});

	describe('Success Message Removal', () => {
		test('should find success message', () => {
			const message = document.querySelector('.learn-press-message.success.lp-content-area');

			expect(message).toBeTruthy();
		});

		test('should remove success message', () => {
			const message = document.querySelector('.learn-press-message.success.lp-content-area');
			message.remove();

			expect(document.querySelector('.learn-press-message.success.lp-content-area')).toBeNull();
		});
	});

	describe('MutationObserver Setup', () => {
		test('should observe DOM changes', () => {
			const mockCallback = jest.fn();
			const observer = new MutationObserver(mockCallback);

			observer.observe(document.body, {
				childList: true,
				subtree: true,
				attributes: true,
				attributeFilter: ['class']
			});

			// Trigger a change
			document.body.classList.add('test-class');

			// Wait for observer to fire
			setTimeout(() => {
				expect(mockCallback).toHaveBeenCalled();
				observer.disconnect();
			}, 10);
		});

		test('should detect button class changes', () => {
			const button = document.querySelector('.lp-button');
			const mockCallback = jest.fn();
			const observer = new MutationObserver(mockCallback);

			observer.observe(button, {
				attributes: true,
				attributeFilter: ['class']
			});

			// Change button class
			button.classList.add('processing');

			setTimeout(() => {
				expect(mockCallback).toHaveBeenCalled();
				observer.disconnect();
			}, 10);
		});
	});

	describe('HTML Cleanup', () => {
		test('should clean retake count from HTML string', () => {
			const html = '<button class="button-retake-course">Retake Course (942)</button>';
			const cleaned = html.replace(/\s*\(\s*\d+\s*\)\s*/g, '');

			expect(cleaned).toBe('<button class="button-retake-course">Retake Course</button>');
		});

		test('should preserve other button content', () => {
			const html = '<button class="button"><span>Complete</span> (123)</button>';
			const cleaned = html.replace(/\s*\(\s*\d+\s*\)\s*/g, '');

			expect(cleaned).toBe('<button class="button"><span>Complete</span></button>');
		});
	});
});
