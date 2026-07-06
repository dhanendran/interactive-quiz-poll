/**
 * Shared Interactivity API store for the poll and quiz blocks.
 *
 * All mutable state lives in maps keyed by post/question ID (never in element
 * context), so nested element contexts only carry identifiers down and writes
 * always land on the intended record.
 */
import { store, getContext, getConfig, getElement } from '@wordpress/interactivity';

const NS = 'interactive-quiz-poll';

const request = async ( path, body ) => {
	const { restUrl } = getConfig( NS );
	const res = await fetch( restUrl + path, {
		method: 'POST',
		headers: { 'Content-Type': 'application/json' },
		body: JSON.stringify( body ),
	} );
	return res.json();
};

const { state } = store( NS, {
	state: {
		polls: {},
		quizzes: {},
		questions: {},

		/* ---- Poll (per-option / per-poll derived values) ---- */
		get pollVoted() {
			const p = state.polls[ getContext().postId ];
			return !! ( p && p.voted );
		},
		get pollLoading() {
			const p = state.polls[ getContext().postId ];
			return !! ( p && p.loading );
		},
		get pollError() {
			const p = state.polls[ getContext().postId ];
			return !! ( p && p.error );
		},
		get pollErrorText() {
			return getConfig( NS ).i18n.error;
		},
		get pollTotalVotesLabel() {
			const p = state.polls[ getContext().postId ];
			const n = p ? p.totalVotes : 0;
			const i18n = getConfig( NS ).i18n;
			return `${ n } ${ 1 === n ? i18n.vote : i18n.votes }`;
		},
		get optionCount() {
			const ctx = getContext();
			const p = state.polls[ ctx.postId ];
			return p && p.counts ? p.counts[ ctx.answerId ] || 0 : 0;
		},
		get optionPercent() {
			const ctx = getContext();
			const p = state.polls[ ctx.postId ];
			if ( ! p || ! p.totalVotes ) {
				return 0;
			}
			return Math.round( ( ( p.counts[ ctx.answerId ] || 0 ) / p.totalVotes ) * 100 );
		},
		get optionWidth() {
			return `${ state.optionPercent }%`;
		},
		get optionPercentLabel() {
			return `${ state.optionPercent }%`;
		},
		get optionIsSelected() {
			const ctx = getContext();
			const p = state.polls[ ctx.postId ];
			return !! ( p && p.selected === ctx.answerId );
		},

		/* ---- Quiz (per-option / per-question / per-quiz derived values) ---- */
		get quizAnswered() {
			const q = state.questions[ getContext().questionId ];
			return !! ( q && q.answered );
		},
		get optionIsCorrect() {
			const ctx = getContext();
			const q = state.questions[ ctx.questionId ];
			return !! ( q && q.answered && q.correctAnswerId === ctx.answerId );
		},
		get optionIsWrongChosen() {
			const ctx = getContext();
			const q = state.questions[ ctx.questionId ];
			return !! ( q && q.answered && q.chosenId === ctx.answerId && q.correctAnswerId !== ctx.answerId );
		},
		get quizHasDetails() {
			const q = state.questions[ getContext().questionId ];
			return !! ( q && q.answered && q.detailsHtml );
		},
		get pollHasDetails() {
			const p = state.polls[ getContext().postId ];
			return !! ( p && p.voted && p.detailsHtml );
		},
		get quizCompleted() {
			const q = state.quizzes[ getContext().postId ];
			return !! ( q && q.completed );
		},
		get quizScoreText() {
			const q = state.quizzes[ getContext().postId ];
			if ( ! q ) {
				return '';
			}
			return `${ getConfig( NS ).i18n.scored } ${ q.correct } / ${ q.total }`;
		},
	},

	actions: {
		*votePoll() {
			const ctx = getContext();
			const p = state.polls[ ctx.postId ];
			if ( ! p || p.voted || p.loading ) {
				return;
			}
			p.loading = true;
			p.error = false;
			try {
				const data = yield request( `/poll/respond/${ ctx.postId }`, {
					questionId: ctx.questionId,
					answerId: ctx.answerId,
				} );
				if ( data && ( data.success || data.alreadyResponded ) ) {
					p.counts = data.counts || {};
					p.totalVotes = data.totalVotes || 0;
					p.selected = data.answerId || ctx.answerId;
					p.detailsHtml = data.questionDetailsHtml || '';
					p.voted = true;
					try {
						window.localStorage.setItem(
							`d9qp_poll_${ ctx.postId }`,
							JSON.stringify( { a: p.selected, t: Date.now() } )
						);
					} catch ( e ) {}
				} else {
					p.error = true;
				}
			} catch ( e ) {
				p.error = true;
			} finally {
				p.loading = false;
			}
		},

		*answerQuiz() {
			const ctx = getContext();
			const q = state.questions[ ctx.questionId ];
			const quiz = state.quizzes[ ctx.postId ];
			if ( ! q || q.answered || q.loading ) {
				return;
			}
			q.loading = true;
			try {
				const data = yield request( `/quiz/respond/${ ctx.postId }`, {
					questionId: ctx.questionId,
					answerId: ctx.answerId,
				} );
				if ( data && data.success ) {
					q.answered = true;
					q.chosenId = ctx.answerId;
					q.correctAnswerId = data.correctAnswerId;
					q.isCorrect = !! data.isCorrect;
					q.detailsHtml = data.questionDetailsHtml || '';
					if ( quiz ) {
						quiz.answered += 1;
						if ( q.isCorrect ) {
							quiz.correct += 1;
						}
						if ( ! quiz.token ) {
							quiz.token = data.token || '';
						}
						if ( quiz.answered >= quiz.total && ! quiz.completed && quiz.token ) {
							const done = yield request( `/quiz/complete/${ ctx.postId }`, {
								token: quiz.token,
							} );
							if ( done && done.success ) {
								quiz.completed = true;
							}
						}
					}
				}
			} catch ( e ) {
				// Leave the question unanswered so the user can retry.
			} finally {
				q.loading = false;
			}
		},
	},

	callbacks: {
		initPoll() {
			const ctx = getContext();
			if ( state.polls[ ctx.postId ] ) {
				return;
			}
			const record = {
				voted: false,
				loading: false,
				error: false,
				selected: null,
				counts: ctx.counts ? { ...ctx.counts } : {},
				totalVotes: ctx.totalVotes || 0,
				detailsHtml: '',
			};
			// Returning visitors: reflect their earlier choice (UX only).
			try {
				const saved = window.localStorage.getItem( `d9qp_poll_${ ctx.postId }` );
				if ( saved ) {
					const parsed = JSON.parse( saved );
					if ( parsed && parsed.a ) {
						record.selected = parsed.a;
						record.voted = true;
					}
				}
			} catch ( e ) {}
			state.polls[ ctx.postId ] = record;
		},

		initQuiz() {
			const ctx = getContext();
			if ( state.quizzes[ ctx.postId ] ) {
				return;
			}
			state.quizzes[ ctx.postId ] = {
				answered: 0,
				correct: 0,
				total: ctx.total || 0,
				token: '',
				completed: false,
			};
		},

		initQuestion() {
			const ctx = getContext();
			if ( state.questions[ ctx.questionId ] ) {
				return;
			}
			state.questions[ ctx.questionId ] = {
				answered: false,
				loading: false,
				chosenId: null,
				correctAnswerId: null,
				isCorrect: false,
				detailsHtml: '',
			};
		},

		renderPollDetails() {
			const { ref } = getElement();
			const p = state.polls[ getContext().postId ];
			if ( ref ) {
				ref.innerHTML = p ? p.detailsHtml || '' : '';
			}
		},

		renderQuizDetails() {
			const { ref } = getElement();
			const q = state.questions[ getContext().questionId ];
			if ( ref ) {
				ref.innerHTML = q ? q.detailsHtml || '' : '';
			}
		},
	},
} );
