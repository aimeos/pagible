import{r as e}from"./graphql-C29JtVna.js";import{Gn as t,In as n,Wn as r,pn as i,wn as a,xn as o}from"./index-f624Xu9_.js";import{toMp3 as s,transcription as c}from"./audio-CFFoow55.js";var l=e`
  mutation ($file: Upload!) {
    transcribe(file: $file)
  }
`,u=e`
  mutation ($texts: [String!]!, $to: String!, $from: String, $context: String) {
    translate(texts: $texts, to: $to, from: $from, context: $context)
  }
`,d=e`
  mutation ($prompt: String!, $context: String, $files: [String!]) {
    write(prompt: $prompt, context: $context, files: $files)
  }
`;function f(e){let u=a(),d=o(),{$gettext:f}=t;if(!u.can(`audio:transcribe`)){d.add(f(`Permission denied`),`error`);return}return s(r(e,!0)).then(e=>i.mutate({mutation:l,variables:{file:new File([e],`audio.mp3`,{type:`audio/mpeg`})},context:{hasUpload:!0}})).then(e=>{if(e.errors)throw e;return c(n(e.data?.transcribe||`[]`,[]))}).catch(e=>{d.add(f(`Error transcribing file`)+`:
`+e,`error`),console.error(`useAi::transcribe(): Error transcribing from media URL`,e)})}function p(e,n,r=null,s=null){let c=a(),l=o(),{$gettext:d}=t;if(!c.can(`text:translate`)){l.add(d(`Permission denied`),`error`);return}return Array.isArray(e)||(e=[e].filter(e=>!!e)),e.length?n?i.mutate({mutation:u,variables:{texts:e,to:n.toUpperCase(),from:r?.toUpperCase(),context:s}}).then(e=>{if(e.errors)throw e;return e.data?.translate||[]}).catch(e=>{l.add(d(`Error translating texts`)+`:
`+e,`error`),console.error(`useAi::translate(): Error translating texts`,e)}):Promise.reject(Error(`Target language is required`)):Promise.resolve([])}function m(e,n=[],r=[]){let s=a(),c=o(),{$gettext:l}=t;if(!s.can(`text:write`)){c.add(l(`Permission denied`),`error`);return}return e=String(e).trim(),e?(Array.isArray(n)||(n=[n]),n.push(`Only return the requested data without any additional information`),i.mutate({mutation:d,variables:{prompt:e,context:n.filter(e=>!!e).join(`
`),files:r.filter(e=>!!e)}}).then(e=>{if(e.errors)throw e;return e.data?.write?.replace(/^"(.*)"$/,`$1`)||``}).catch(e=>{c.add(l(`Error generating text`)+`:
`+e,`error`),console.error(`useAi::write(): Error generating text`,e)})):(c.add(l(`Prompt is required for generating text`),`error`),Promise.resolve(``))}export{f as transcribe,p as translate,m as write};