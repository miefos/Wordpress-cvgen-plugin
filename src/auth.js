import React, { useState, useEffect } from 'react'
import axios from 'axios'
import ReactDOM from 'react-dom'
import { store } from './store/mainStore'
import {Provider, useDispatch, useSelector} from 'react-redux'
import {
  decreaseASecondUntilCanResend,
  resetState,
  setApiResponse,
  setEmail,
  setNonce,
  setNonceName,
  setOTP, setSecondsUntilCanResend,
  setShouldEnterOTP, setShouldShowCanResendIn, setShouldShowResend
} from "./features/auth/authSlicer";

document.addEventListener("DOMContentLoaded", function () {
  const root = document.getElementById("auth_form")
  const data = JSON.parse(root.querySelector("pre").innerHTML)

  ReactDOM.render(
    <Provider store={store}>
      <Message />
      <AuthForm {...data}/>
    </Provider>,
    root
  )
})

function Message() {
  const response = useSelector((state) => state.auth.apiResponse)
  if (!response) return;

  return (
    <div className={`${response.status === 'ok' ? "ok-status-info" : "fail-status-info"}`}>
      { response.msg }
    </div>
  )
}

function AuthForm(props) {
  const shouldEnterOTP = useSelector((state) => state.auth.shouldEnterOTP)
  const dispatch = useDispatch()

  useEffect(() => {
    dispatch(setNonce(props.nonce))
    dispatch(setEmail(props.email))
    dispatch(setNonceName(props.nonce_name))
  }, [])

  return (
    <div>
      <EmailField {...props} />
      <div>
        {
          shouldEnterOTP
            ?
          <div>
            <OTPField {...props} />
            <input type="submit" onClick={authorizeOTP} value={props.submit_attempt_email_otp} />
            <ResendAssistance {...props}/>
          </div>
          :
            <div>
              <input type="submit" onClick={() => authorizeEmail(props)} value={props.submit_email} />
            </div>
        }
      </div>
    </div>
  )
}

function ResendAssistance(props) {
  const shouldShowResend = useSelector((state) => state.auth.shouldShowResend)
  const shouldShowCanResendIn = useSelector((state) => state.auth.shouldShowCanResendIn)
  const secondsUntilCanResend = useSelector((state) => state.auth.secondsUntilCanResend)

  return (
    <div>
      {shouldShowCanResendIn && secondsUntilCanResend > 0 ? <div><small>{props.wait_until_can_be_resent} {secondsUntilCanResend}</small></div> : null}
      {shouldShowResend && secondsUntilCanResend <= 0 ? <div onClick={() => authorizeEmail(props)}><small className="clickableElem" >{props.resend_label}</small></div> : null}
    </div>
  )
}

function authorizeOTP() {
  const email = store.getState().auth.email
  const nonceName = store.getState().auth.nonceName
  const nonce = store.getState().auth.nonce
  const otp = store.getState().auth.otp
  const data = {
    email: email,
    [nonceName]: nonce,
    otp: otp
  }

  axios.post('/wp-json/cvgen/auth/attempt_otp', data)
    .then(function (response) {
      store.dispatch(setApiResponse(response.data))
      if (response.data.status === 'ok') {
        location.reload()
      }
    })
    .catch(function (error) {
      console.log("Error authorizing OTP");
    });
}

function authorizeEmail(props) {
  const email = store.getState().auth.email
  const nonceName = store.getState().auth.nonceName
  const nonce = store.getState().auth.nonce
  const data = {
    email: email,
    [nonceName]: nonce
  }

  store.dispatch(setShouldShowCanResendIn(false))
  store.dispatch(setShouldShowResend(false))

  axios.post('/wp-json/cvgen/auth/send_otp', data)
    .then(function (response) {
      store.dispatch(setApiResponse(response.data))
      if (response.data.status === 'ok') {
        store.dispatch(setShouldEnterOTP(true))
      }
      setTimeout(function(){
        store.dispatch(setShouldShowCanResendIn(true))
        store.dispatch(setSecondsUntilCanResend(props.waiting_time_until_can_be_resent))
        let secondsCounter = setInterval(() => {
          store.dispatch(decreaseASecondUntilCanResend())
          if (store.getState().auth.secondsUntilCanResend <= 0 ) {
            clearInterval(secondsCounter)
            store.dispatch(setShouldShowResend(true))
          }
        }, 1000);
      }, props.waiting_time_until_info_about_can_be_resent_is_shown * 1000)
    })
    .catch(function (error) {
      console.log("Error sending OTP");
    });
}

function OTPField(props) {
  const dispatch = useDispatch()

  return (
    <div>
      <div>
        <label htmlFor="email">{props.otp_label}</label>
      </div>
      <div>
        <input onKeyUp={(e) => {if (e.key === "Enter") authorizeOTP()} } type="number" name="otp" id="otp" onChange={(e) => dispatch(setOTP(e.target.value))} />
      </div>
    </div>
  )
}

function EmailField(props) {
  const defaultEmail = props.email

  return (
    <div>
      <div>
        <label htmlFor="email">{props.email_label}</label>
      </div>
      <div>
        <input onKeyUp={(e) => {if (e.key === "Enter") authorizeEmail(props)} }  type="email" name="email" id="email" defaultValue={defaultEmail} onChange={(e) => emailChanged(e.target.value)} />
      </div>
    </div>
  )
}

function emailChanged(newEmail) {
  const shouldEnterOTP = store.getState().auth.shouldEnterOTP
  if (shouldEnterOTP) {
    store.dispatch(resetState())
  } else {
    store.dispatch(setEmail(newEmail))
  }
}