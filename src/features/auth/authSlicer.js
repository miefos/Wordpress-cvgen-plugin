import { createSlice } from '@reduxjs/toolkit'

const initialState = {
  email: '',
  otp: '',
  nonce: '',
  nonceName: '',
  shouldEnterOTP: false,
  shouldShowResend: false,
  shouldShowCanResendIn: false,
  secondsUntilCanResend: null,
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
      state.apiResponse = action.payload
    },
    setShouldEnterOTP: (state, action) => {
      state.shouldEnterOTP = action.payload
    },
    setShouldShowResend: (state, action) => {
      state.shouldShowResend = action.payload
    },
    setShouldShowCanResendIn: (state, action) => {
      state.shouldShowCanResendIn = action.payload
    },
    setSecondsUntilCanResend: (state, action) => {
      state.secondsUntilCanResend = action.payload
    },
    decreaseASecondUntilCanResend: (state) => {
      state.secondsUntilCanResend -= 1
    },
    resetState: (state, action) => {
      const except = ['none', 'nonceName']

      Object.keys(initialState).forEach(key => {
        if (!except.includes(key)) {
          state[key] = initialState[key]
        }
      })
    }
  },
})

export const { setOTP, setEmail, setNonce, setNonceName, setApiResponse, setShouldEnterOTP, setShouldShowResend, setShouldShowCanResendIn, resetState, setSecondsUntilCanResend, decreaseASecondUntilCanResend } = counterSlice.actions

export default counterSlice.reducer