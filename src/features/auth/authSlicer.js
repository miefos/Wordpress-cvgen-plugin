import { createSlice } from '@reduxjs/toolkit'

const initialState = {
  email: '',
  otp: '',
  nonce: '',
  nonceName: '',
  apiResponse: {msg: null, status: null},
}

export const counterSlice = createSlice({
  name: 'auth',
  initialState,
  reducers: {
    setEmail: (state, action) => {
      state.email = action.payload
    },
    setOTP: (state, action) => {
      state.otp = action.payload
    },
    setNonce: (state, action) => {
      state.nonce = action.payload
    },
    setNonceName: (state, action) => {
      state.nonceName = action.payload
    },
    setApiResponse: (state, action) => {
      console.log(action)
      state.apiResponse = action.payload
    },
  },
})

export const { setOTP, setEmail, setNonce, setNonceName, setApiResponse } = counterSlice.actions

export default counterSlice.reducer