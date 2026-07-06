/**
 * Shared Interactivity API store for the poll and quiz blocks.
 *
 * All mutable state lives in maps keyed by the display post (`contextId`) so the
 * same quiz/poll embedded on two posts tracks independently. Element contexts
 * only carry identifiers (`refId`, `contextId`, `questionId`, `answerId`) down;
 * writes always land on the intended record.
 */
import { store, getContext, getConfig, getElement } from '@wordpress/interactivity';

const NS = 'interactive-quiz-poll';

// Per-context localStorage snapshot, memoised so it is available to whichever
// init callback runs first (child `data-wp-init` effects run before parents').
const savedCache = {};
// Question IDs seen per context, tracked independently of the quiz record so it
// survives init ordering.
const contextQuestions = {};

const loadSaved = ( contextId ) => {
	if ( ! ( contextId in savedCache ) ) {
		let parsed = null;
		try {
			parsed = JSON.parse( window.localStorage.getItem( `d9qp_quiz_${ contextId }` ) );
		} catch ( e ) {}
		savedCache[ contextId ] = parsed || null;
	}
	return savedCache[ contextId ];
};

const trackQuestion = ( contextId, questionId ) => {
	if ( ! contextQuestions[ contextId ] ) {
		contextQuestions[ contextId ] = [];
	}
	if ( contextQuestions[ contextId ].indexOf( questionId ) < 0 ) {
		contextQuestions[ contextId ].push( questionId );
	}
};

const request = async ( path, body ) => {
	const { restUrl } = getConfig( NS );
	const res = await fetch( restUrl + path, {
		method: 'POST',
		headers: { 'Content-Type': 'application/json' },
		credentials: 'same-origin',
		body: JSON.stringify( body ),
	} );
	return res.json();
};

const persistQuiz = ( contextId ) => {
	const quiz = state.quizzes[ contextId ];
	if ( ! quiz ) {
		return;
	}
	const data = {
		answered: quiz.answered,
		correct: quiz.correct,
		total: quiz.total,
		completed: quiz.completed,
		token: quiz.token,
		questions: {},
	};
	( contextQuestions[ contextId ] || [] ).forEach( ( qid ) => {
		const q = state.questions[ qid ];
		if ( q && q.answered ) {
			data.questions[ qid ] = {
				chosenId: q.chosenId,
				correctAnswerId: q.correctAnswerId,
				isCorrect: q.isCorrect,
				detailsHtml: q.detailsHtml,
			};
		}
	} );
	try {
		window.localStorage.setItem( `d9qp_quiz_${ contextId }`, JSON.stringify( data ) );
	} catch ( e ) {}
};

const { state } = store( NS, {
	state: {
		polls: {},
		quizzes: {},
		questions: {},

		/* ---- Poll (per-option / per-poll derived values) ---- */
		get pollVoted() {
			const p = state.polls[ getContext().contextId ];
			return !! ( p && p.voted );
		},
		get pollLoading() {
			const p = state.polls[ getContext().contextId ];
			return !! ( p && p.loading );
		},
		get pollError() {
			const p = state.polls[ getContext().contextId ];
			return !! ( p && p.error );
		},
		get pollErrorText() {
			return getConfig( NS ).i18n.error;
		},
		get pollHasDetails() {
			const p = state.polls[ getContext().contextId ];
			return !! ( p && p.voted && p.detailsHtml );
		},
		get pollTotalVotesLabel() {
			const p = state.polls[ getContext().contextId ];
			const n = p ? p.totalVotes : 0;
			const i18n = getConfig( NS ).i18n;
			return `${ n } ${ 1 === n ? i18n.vote : i18n.votes }`;
		},
		get optionCount() {
			const ctx = getContext();
			const p = state.polls[ ctx.contextId ];
			return p && p.counts ? p.counts[ ctx.answerId ] || 0 : 0;
		},
		get optionPercent() {
			const ctx = getContext();
			const p = state.polls[ ctx.contextId ];
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
			const p = state.polls[ ctx.contextId ];
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
		get quizCompleted() {
			const q = state.quizzes[ getContext().contextId ];
			return !! ( q && q.completed );
		},
		get quizScoreText() {
			const q = state.quizzes[ getContext().contextId ];
			if ( ! q ) {
				return '';
			}
			return `${ getConfig( NS ).i18n.scored } ${ q.correct } / ${ q.total }`;
		},
	},

	actions: {
		*votePoll() {
			const ctx = getContext();
			const p = state.polls[ ctx.contextId ];
			if ( ! p || p.voted || p.loading ) {
				return;
			}
			p.loading = true;
			p.error = false;
			try {
				const data = yield request( `/poll/respond/${ ctx.refId }`, {
					contextId: ctx.contextId,
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
							`d9qp_poll_${ ctx.contextId }`,
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
			const quiz = state.quizzes[ ctx.contextId ];
			if ( ! q || q.answered || q.loading ) {
				return;
			}
			q.loading = true;
			try {
				const data = yield request( `/quiz/respond/${ ctx.refId }`, {
					contextId: ctx.contextId,
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
						trackQuestion( ctx.contextId, ctx.questionId );
						quiz.answered += 1;
						if ( q.isCorrect ) {
							quiz.correct += 1;
						}
						if ( ! quiz.token ) {
							quiz.token = data.token || '';
						}
						if ( quiz.answered >= quiz.total && ! quiz.completed && quiz.token ) {
							const done = yield request( `/quiz/complete/${ ctx.refId }`, {
								contextId: ctx.contextId,
								token: quiz.token,
							} );
							if ( done && done.success ) {
								quiz.completed = true;
							}
						}
						persistQuiz( ctx.contextId );
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
			if ( state.polls[ ctx.contextId ] ) {
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
				const saved = window.localStorage.getItem( `d9qp_poll_${ ctx.contextId }` );
				if ( saved ) {
					const parsed = JSON.parse( saved );
					if ( parsed && parsed.a ) {
						record.selected = parsed.a;
						record.voted = true;
					}
				}
			} catch ( e ) {}
			state.polls[ ctx.contextId ] = record;
		},

		initQuiz() {
			const ctx = getContext();
			const id = ctx.contextId;
			if ( state.quizzes[ id ] ) {
				return;
			}
			const saved = loadSaved( id );
			state.quizzes[ id ] = {
				answered: saved ? saved.answered || 0 : 0,
				correct: saved ? saved.correct || 0 : 0,
				total: ctx.total || 0,
				token: saved ? saved.token || '' : '',
				completed: saved ? !! saved.completed : false,
			};
		},

		initQuestion() {
			const ctx = getContext();
			const qid = ctx.questionId;
			trackQuestion( ctx.contextId, qid );
			if ( state.questions[ qid ] ) {
				return;
			}
			const savedData = loadSaved( ctx.contextId );
			const savedQuestions = savedData && savedData.questions ? savedData.questions : {};
			const saved = savedQuestions[ qid ];
			state.questions[ qid ] = saved
				? {
						answered: true,
						loading: false,
						chosenId: saved.chosenId,
						correctAnswerId: saved.correctAnswerId,
						isCorrect: saved.isCorrect,
						detailsHtml: saved.detailsHtml,
				  }
				: {
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
			const p = state.polls[ getContext().contextId ];
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
