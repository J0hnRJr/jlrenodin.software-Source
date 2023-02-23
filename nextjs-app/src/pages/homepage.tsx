import React from "react";
import Link from "next/link";

const ExampleApp = () => {
  const headerText = "React";
  const contentText =
    "This page is generated using React and Typescript. See the source code at ";
  const sourceText =
    "https://github.com/J0hnRJr/jlrenodin.software-Source/nextjs-app/src/app/pages/homepage.tsx";
  return (
    <div>
      <h1>{headerText}</h1>
      <p>
        {contentText}
        <Link href={sourceText}>{"this link"}</Link>
      </p>
    </div>
  );
};

export default ExampleApp;
