import requester from '@/globals/requester';

const getDefaults = () => ({
    'eventSummary': {
        'customText': {
            'title': null,
            'content': null,
        },
        'materialDisplayMode': 'sub-categories',
    },
    'calendar': {
        'event': {
            'showLocation': true,
            'showBorrower': false,
        },
    },
});

export default {
    namespaced: true,
    state: getDefaults(),
    mutations: {
        reset(state) {
            Object.assign(state, getDefaults());
        },
        set(state, data) {
            Object.assign(state, data);
        },
    },
    actions: {
        reset({ commit }) {
            commit('reset');
        },
        async fetch({ commit }) {
            const { data } = await requester.get('/settings');
            commit('set', data);
        },
    },
};