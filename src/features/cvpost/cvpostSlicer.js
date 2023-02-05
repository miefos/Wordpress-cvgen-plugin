import { createSlice } from '@reduxjs/toolkit'

const initialState = {
  nonce: '',
  nonceName: '',
  Name_text: '',
  Surname_text: '',
  Phone_tel: '',
  Languages_repeatable: [],
  repeatableInnerLastIds: {},
  apiResponse: {msg: null, status: null},
}

export const counterSlice = createSlice({
  name: 'cvpost',
  initialState,
  reducers: {
    setNonce: (state, action) => {
      state.nonce = action.payload
    },
    setNonceName: (state, action) => {
      state.nonceName = action.payload
    },
    setApiResponse: (state, action) => {
      state.apiResponse = action.payload
    },
    setField: (state, action) => {
      const fieldId = action.payload.field.id
      state[fieldId] = action.payload.value
    },
    addRepeatableFieldRow: (state, action) => {
      const fieldId = action.payload.field.id
      if (fieldId in state.repeatableInnerLastIds) {
        state.repeatableInnerLastIds[fieldId] += 1
      } else {
        state.repeatableInnerLastIds[fieldId] = 0
      }

      const row = {...action.payload.value, _inner_id: state.repeatableInnerLastIds[fieldId]}

      state[fieldId] = [].concat(state[fieldId], [row])
    },
    updateLanguageField: (state, action) => {
      const index = action.payload._inner_id
      const fieldId = action.payload.field.id
      const newVal = action.payload.newVal
      state.Languages_repeatable[index] = {...state.Languages_repeatable[index], [fieldId]: newVal}
    },
    deleteLanguageField: (state, action) => {
      state.Languages_repeatable.splice(action.payload, 1)
    },
    setInitialRepeatableField: (state, action) => {
      const fieldId = action.payload.field.id

      let i = 0
      action.payload.value.forEach(row => {
        row['_inner_id'] = i
        state[fieldId] = [].concat(state[fieldId], [row])
        i++
      })
      i-- // undo last addition

      state.repeatableInnerLastIds[fieldId] = i
    }
  },
})

export const {
  setNonce,
  setNonceName,
  setApiResponse,
  setField,
  addRepeatableFieldRow,
  updateLanguageField,
  setInitialRepeatableField,
  deleteLanguageField
} = counterSlice.actions

export default counterSlice.reducer