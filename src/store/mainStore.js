import { configureStore } from '@reduxjs/toolkit'
import authReducer from '../features/auth/authSlicer'
import cvpostReducer from "../features/cvpost/cvpostSlicer";

export const store = configureStore({
  reducer: {
    auth: authReducer,
    cvpost: cvpostReducer
  },
})

